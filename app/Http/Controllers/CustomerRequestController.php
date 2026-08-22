<?php

namespace App\Http\Controllers;

use App\Enums\CustomerRequests\Status;
use App\Http\Requests\CustomerRequestFormRequest;
use App\Models\CustomerRequest;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use App\Models\RequestImage;
use App\Services\CategoryService;
use App\Services\CustomerRequestService;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CustomerRequestController extends Controller
{
    public function __construct(
        public CustomerRequestService $customerRequestService,
        public CustomerService $customerService,
        public CategoryService $categoryService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', CustomerRequest::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $customerPublicId = $request->input('customer') ? (string) $request->input('customer') : null;

        return Inertia::render('CustomerRequests/CustomerRequestsPage', [
            'requests' => $this->customerRequestService->getPaginatedRequests(
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
                customerPublicId: $customerPublicId,
            ),
            'customers' => $this->customerService->optionsForRequests(activeOnly: false),
            'availableCategories' => $this->categoryService->activeCategoriesForAssignment(),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
                'customer' => $customerPublicId,
            ],
            'statuses' => Status::toArray(),
        ]);
    }

    public function store(CustomerRequestFormRequest $request)
    {
        $this->customerRequestService->store(
            data: $request->validated(),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(CustomerRequestFormRequest $request, CustomerRequest $customerRequest)
    {
        $this->customerRequestService->update(
            customerRequest: $customerRequest,
            data: $request->validated(),
            image: $request->file('image'),
            removeImage: $request->boolean('remove_image'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function image(CustomerRequest $customerRequest)
    {
        $this->authorize('view', $customerRequest);

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

    public function offerImage(CustomerRequest $customerRequest, MerchantOffer $merchantOffer, MerchantOfferImage $offerImage)
    {
        $this->authorize('view', $customerRequest);
        abort_unless((int) $merchantOffer->customer_request_id === (int) $customerRequest->id, 404);
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
}
