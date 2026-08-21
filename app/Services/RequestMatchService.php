<?php

namespace App\Services;

use App\Enums\RequestMatches\Status as MatchStatus;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\RequestMatch;
use App\Support\MerchantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class RequestMatchService
{
    public function __construct(
        public MerchantContext $merchantContext,
        public RequestMatchingService $requestMatchingService,
    ) {}

    public function getPaginatedMatches(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        ?int $status = null,
        ?string $merchantPublicId = null,
        ?string $requestPublicId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'status', 'matched_at', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return RequestMatch::query()
            ->with([
                'customerRequest:id,public_id,request_text,status,category_id,customer_id,created_at',
                'customerRequest.customer:id,public_id,name',
                'customerRequest.category:id,public_id,name_ar,name_en',
                'merchant:id,public_id,name,status',
            ])
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($merchantPublicId, function ($q) use ($merchantPublicId) {
                $q->whereHas('merchant', fn ($merchant) => $merchant->where('public_id', $merchantPublicId));
            })
            ->when($requestPublicId, function ($q) use ($requestPublicId) {
                $q->whereHas('customerRequest', fn ($request) => $request->where('public_id', $requestPublicId));
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->whereHas('customerRequest', function ($request) use ($search) {
                        $request->where('request_text', 'like', "%{$search}%")
                            ->orWhere('public_id', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($customer) use ($search) {
                                $customer->where('name', 'like', "%{$search}%");
                            });
                    })->orWhereHas('merchant', function ($merchant) use ($search) {
                        $merchant->where('name', 'like', "%{$search}%")
                            ->orWhere('public_id', 'like', "%{$search}%");
                    });
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getPaginatedMerchantRequests(
        string $search = '',
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $merchant = $this->requireActiveMerchant();
        $allowedSorts = ['created_at', 'id'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';

        return CustomerRequest::query()
            ->select(['id', 'public_id', 'request_text', 'status', 'category_id', 'created_at'])
            ->with(['category:id,public_id,name_ar,name_en', 'image'])
            ->whereHas('matches', function ($query) use ($merchant) {
                $query->where('merchant_id', $merchant->id)
                    ->visibleToMerchant();
            })
            ->with(['matches' => function ($query) use ($merchant) {
                $query->where('merchant_id', $merchant->id)
                    ->visibleToMerchant();
            }])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('request_text', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($category) use ($search) {
                            $category->where('name_ar', 'like', "%{$search}%")
                                ->orWhere('name_en', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CustomerRequest $request) => $this->presentForMerchant($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForMerchant(CustomerRequest $customerRequest, bool $includeImageUrl = true): array
    {
        $merchant = $this->requireActiveMerchant();
        $customerRequest->loadMissing(['category:id,public_id,name_ar,name_en', 'image']);

        $match = $customerRequest->relationLoaded('matches')
            ? $customerRequest->matches->firstWhere('merchant_id', $merchant->id)
            : $this->requestMatchingService->currentMerchantMatch($customerRequest);

        $hasImage = $customerRequest->image !== null;

        return [
            'public_id' => $customerRequest->public_id,
            'request_text' => $customerRequest->request_text,
            'created_at' => $customerRequest->created_at,
            'category' => $customerRequest->category === null ? null : [
                'public_id' => $customerRequest->category->public_id,
                'name_ar' => $customerRequest->category->name_ar,
                'name_en' => $customerRequest->category->name_en,
            ],
            'has_image' => $hasImage,
            'image_url' => $hasImage && $includeImageUrl
                ? route('merchant.requests.image', $customerRequest)
                : null,
            'match_status' => $match?->status_formatted,
        ];
    }

    public function parseStatusFilter(mixed $status): ?int
    {
        if ($status === null || $status === '') {
            return null;
        }

        if (! is_numeric($status)) {
            throw ValidationException::withMessages([
                'status' => 'The selected status is invalid.',
            ]);
        }

        $value = (int) $status;

        if (MatchStatus::tryFrom($value) === null) {
            throw ValidationException::withMessages([
                'status' => 'The selected status is invalid.',
            ]);
        }

        return $value;
    }

    private function requireActiveMerchant(): Merchant
    {
        if (! $this->merchantContext->isActive()) {
            abort(403);
        }

        /** @var Merchant $merchant */
        $merchant = $this->merchantContext->merchant();

        return $merchant;
    }
}
