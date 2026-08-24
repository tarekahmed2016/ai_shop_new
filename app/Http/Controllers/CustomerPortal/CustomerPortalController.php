<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerPortalClassificationConfirmRequest;
use App\Http\Requests\CustomerPortalClassificationRequest;
use App\Http\Requests\CustomerPortalClassificationRetryRequest;
use App\Http\Requests\CustomerPortalPendingCategoryRequest;
use App\Http\Requests\CustomerPortalProfileUpdateRequest;
use App\Http\Requests\CustomerPortalRequestStoreRequest;
use App\Models\CustomerRequest;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use App\Models\RequestClassification;
use App\Models\RequestImage;
use App\Services\CategoryService;
use App\Services\CustomerPortalService;
use App\Services\MerchantOfferService;
use App\Services\RequestClassificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPortalController extends Controller
{
    public function __construct(
        public CustomerPortalService $customerPortalService,
        public CategoryService $categoryService,
        public MerchantOfferService $merchantOfferService,
        public RequestClassificationService $requestClassificationService,
    ) {}

    public function home(): Response
    {
        $customer = $this->customerPortalService->requireCustomer();

        return Inertia::render('CustomerPortal/HomePage', [
            'customer' => [
                'public_id' => $customer->public_id,
                'name' => $customer->name,
            ],
            'stats' => $this->customerPortalService->dashboardStats($customer),
            'recentRequests' => $this->customerPortalService->recentRequests($customer),
            'requestQuota' => $this->customerPortalService->customerRequestLimitService->snapshot($customer),
        ]);
    }

    public function requestsIndex(Request $request): Response
    {
        $customer = $this->customerPortalService->requireCustomer();
        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('CustomerPortal/RequestsIndexPage', [
            'requests' => $this->customerPortalService->getPaginatedOwnRequests(
                customer: $customer,
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

    public function requestsCreate(): Response
    {
        $customer = $this->customerPortalService->requireCustomer();

        return Inertia::render('CustomerPortal/RequestCreatePage', [
            'classification' => null,
            'pendingRequest' => null,
            'classificationError' => null,
            'requestQuota' => $this->customerPortalService->customerRequestLimitService->snapshot($customer),
        ]);
    }

    public function requestsStore(CustomerPortalRequestStoreRequest $request): RedirectResponse
    {
        $customer = $this->customerPortalService->requireCustomer();

        $this->customerPortalService->createRequest(
            customer: $customer,
            data: $request->validated(),
            image: $request->file('image'),
        );

        return redirect()
            ->route('customer.requests.create')
            ->with('success', 'تم إنشاء الطلب بنجاح');
    }

    public function requestsClassify(CustomerPortalClassificationRequest $request): Response
    {
        $customer = $this->customerPortalService->requireCustomer();

        $classification = $this->requestClassificationService->classify(
            customer: $customer,
            data: $request->validated(),
            image: $request->file('image'),
        );

        $message = $classification->status?->name === 'Failed'
            ? __('We couldn\'t determine the category automatically. Add more details and try again.')
            : null;

        return Inertia::render('CustomerPortal/RequestCreatePage', [
            'classification' => $this->requestClassificationService->presentForCustomer($classification),
            'pendingRequest' => [
                'public_id' => $classification->customerRequest?->public_id,
                'request_text' => $classification->customerRequest?->request_text,
                'has_image' => $classification->customerRequest?->image !== null,
            ],
            'classificationError' => $message,
            'requestQuota' => $this->customerPortalService->customerRequestLimitService->snapshot($customer),
        ]);
    }

    public function requestsClassificationConfirm(
        CustomerPortalClassificationConfirmRequest $request,
        RequestClassification $requestClassification
    ): RedirectResponse {
        $customer = $this->customerPortalService->requireCustomer();

        $created = $this->requestClassificationService->confirm(
            customer: $customer,
            classification: $requestClassification,
            categoryPublicId: $request->validated('category_id'),
        );

        return redirect()
            ->route('customer.requests.show', $created)
            ->with('success', 'تم إنشاء الطلب بنجاح');
    }

    public function requestsRetryClassification(
        CustomerPortalClassificationRetryRequest $request,
        CustomerRequest $customerRequest
    ): RedirectResponse {
        $customer = $this->customerPortalService->requireCustomer();
        $owned = $this->customerPortalService->findOwnRequestOrFail($customer, $customerRequest);

        $this->requestClassificationService->retry(
            customer: $customer,
            request: $owned,
            data: $request->validated(),
            image: $request->file('image'),
        );

        return redirect()
            ->route('customer.requests.show', $owned)
            ->with('success', 'تم تحديث تحليل الطلب');
    }

    public function requestsFinalizeCategory(
        CustomerPortalPendingCategoryRequest $request,
        CustomerRequest $customerRequest
    ): RedirectResponse {
        $customer = $this->customerPortalService->requireCustomer();
        $owned = $this->customerPortalService->findOwnRequestOrFail($customer, $customerRequest);

        $finalized = $this->requestClassificationService->finalizeWithCategory(
            customer: $customer,
            request: $owned,
            categoryPublicId: $request->validated('category_id'),
        );

        return redirect()
            ->route('customer.requests.show', $finalized)
            ->with('success', 'تم إنشاء الطلب بنجاح');
    }

    public function requestsShow(CustomerRequest $customerRequest): Response
    {
        $customer = $this->customerPortalService->requireCustomer();
        $owned = $this->customerPortalService->findOwnRequestOrFail($customer, $customerRequest);
        $classification = $this->requestClassificationService->presentLatestForPendingRequest($owned);

        return Inertia::render('CustomerPortal/RequestShowPage', [
            'request' => [
                'public_id' => $owned->public_id,
                'request_text' => $owned->request_text,
                'status' => $owned->status?->value,
                'status_formatted' => $owned->status_formatted,
                'source' => $owned->source?->value,
                'created_at' => $owned->created_at,
                'category' => $owned->category ? [
                    'public_id' => $owned->category->public_id,
                    'name_ar' => $owned->category->name_ar,
                    'name_en' => $owned->category->name_en,
                ] : null,
                'has_image' => $owned->image !== null,
                'offers_count' => $owned->submittedOffers()->count(),
                'can_resume_classification' => $classification !== null,
            ],
            'classification' => $classification,
            'offers' => $classification !== null
                ? []
                : $this->merchantOfferService->presentSubmittedForCustomer($owned),
        ]);
    }

    public function requestsImage(CustomerRequest $customerRequest): StreamedResponse
    {
        $customer = $this->customerPortalService->requireCustomer();
        $owned = $this->customerPortalService->findOwnRequestOrFail($customer, $customerRequest);

        $image = $owned->image;
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

    public function profileEdit(): Response
    {
        $customer = $this->customerPortalService->requireCustomer();
        $user = $customer->user;

        return Inertia::render('CustomerPortal/ProfilePage', [
            'user' => [
                'name' => $user?->name,
                'email' => $user?->email,
                'phone' => $user?->phone,
            ],
        ]);
    }

    public function profileUpdate(CustomerPortalProfileUpdateRequest $request): RedirectResponse
    {
        $customer = $this->customerPortalService->requireCustomer();
        $this->customerPortalService->updateProfile($customer, $request->validated());

        return redirect()->route('customer.profile.edit')->with('success', 'تم تحديث الملف الشخصي بنجاح');
    }
}
