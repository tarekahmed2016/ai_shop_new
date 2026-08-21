<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use App\Models\RequestImage;
use App\Services\RequestMatchingService;
use App\Services\RequestMatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MerchantRequestController extends Controller
{
    public function __construct(
        public RequestMatchService $requestMatchService,
        public RequestMatchingService $requestMatchingService,
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

        return Inertia::render('Merchants/MerchantRequestShowPage', [
            'request' => $this->requestMatchService->presentForMerchant($customerRequest),
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
}
