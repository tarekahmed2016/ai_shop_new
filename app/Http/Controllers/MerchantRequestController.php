<?php

namespace App\Http\Controllers;

use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Http\Requests\MerchantOfferStoreRequest;
use App\Http\Requests\MerchantOfferUpdateRequest;
use App\Models\CustomerRequest;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use App\Models\RequestImage;
use App\Services\MerchantOfferService;
use App\Services\MerchantPermissionService;
use App\Services\RequestMatchingService;
use App\Services\RequestMatchService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MerchantRequestController extends Controller
{
    public function __construct(
        public RequestMatchService $requestMatchService,
        public RequestMatchingService $requestMatchingService,
        public MerchantOfferService $merchantOfferService,
        public MerchantPermissionService $merchantPermissionService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewMatchedAny', CustomerRequest::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('Merchants/MerchantRequestsPage', [
            'requests' => $this->requestMatchService->getPaginatedMerchantRequests(
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
            ),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function show(CustomerRequest $customerRequest)
    {
        $this->authorize('viewMatched', $customerRequest);

        $this->requestMatchingService->markViewed($customerRequest);
        $customerRequest->unsetRelation('matches');

        $offer = $this->merchantOfferService->currentOffer($customerRequest);

        return Inertia::render('Merchants/MerchantRequestShowPage', [
            'request' => $this->requestMatchService->presentForMerchant($customerRequest),
            'offer' => $this->merchantOfferService->presentForMerchant($offer),
            'availabilityStatuses' => AvailabilityStatus::toArray(),
            'offerPermissions' => [
                'view' => $this->merchantPermissionService->currentCan(PermissionKey::OffersView->value),
                'create' => $this->merchantPermissionService->currentCan(PermissionKey::OffersCreate->value),
                'update' => $this->merchantPermissionService->currentCan(PermissionKey::OffersUpdate->value),
                'withdraw' => $this->merchantPermissionService->currentCan(PermissionKey::OffersWithdraw->value),
            ],
        ]);
    }

    public function image(CustomerRequest $customerRequest)
    {
        $this->authorize('viewMatched', $customerRequest);

        $image = $customerRequest->image;
        abort_unless($image instanceof RequestImage, 404);
        abort_unless(is_string($image->path) && $image->path !== '', 404);
        abort_unless(Storage::disk(RequestImage::DISK)->exists($image->path), 404);

        return Storage::disk(RequestImage::DISK)->response(
            $image->path,
            $image->original_name,
            [
                'Content-Type' => $image->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function dismiss(CustomerRequest $customerRequest)
    {
        $this->authorize('dismissMatched', $customerRequest);

        $this->requestMatchingService->dismissForCurrentMerchant($customerRequest);

        return redirect()->route('merchant.requests.index')->with('success', 'تم إخفاء الطلب');
    }

    public function storeOffer(MerchantOfferStoreRequest $request, CustomerRequest $customerRequest)
    {
        $this->authorize('viewMatched', $customerRequest);

        $this->merchantOfferService->submit(
            $customerRequest,
            $request->validated(),
            $this->uploadedImages($request, 'images'),
        );

        return redirect()
            ->route('merchant.requests.show', $customerRequest)
            ->with('success', 'تم إرسال العرض');
    }

    public function updateOffer(MerchantOfferUpdateRequest $request, CustomerRequest $customerRequest)
    {
        $this->authorize('viewMatched', $customerRequest);

        $this->merchantOfferService->update(
            $customerRequest,
            $request->validated(),
            $this->uploadedImages($request, 'images'),
            array_map('intval', $request->input('remove_image_ids', []) ?: []),
        );

        return redirect()
            ->route('merchant.requests.show', $customerRequest)
            ->with('success', 'تم تحديث العرض');
    }

    public function withdrawOffer(CustomerRequest $customerRequest)
    {
        $this->authorize('viewMatched', $customerRequest);

        $this->merchantOfferService->withdraw($customerRequest);

        return redirect()
            ->route('merchant.requests.show', $customerRequest)
            ->with('success', 'تم سحب العرض');
    }

    public function offerImage(MerchantOffer $merchantOffer, MerchantOfferImage $offerImage): StreamedResponse
    {
        $this->authorize('viewImage', [$merchantOffer, $offerImage]);

        abort_unless(is_string($offerImage->path) && $offerImage->path !== '', 404);
        abort_unless(Storage::disk(MerchantOfferImage::DISK)->exists($offerImage->path), 404);

        return Storage::disk(MerchantOfferImage::DISK)->response(
            $offerImage->path,
            $offerImage->original_name,
            [
                'Content-Type' => $offerImage->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedImages(Request $request, string $key): array
    {
        $files = $request->file($key, []);

        if ($files === null) {
            return [];
        }

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
    }
}
