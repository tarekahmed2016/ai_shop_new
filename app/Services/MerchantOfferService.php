<?php

namespace App\Services;

use App\Enums\CustomerRequests\Status as RequestStatus;
use App\Enums\MerchantOffers\AvailabilityStatus;
use App\Enums\MerchantOffers\Status as OfferStatus;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Models\CustomerRequest;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use App\Models\RequestMatch;
use App\Support\MerchantContext;
use App\Support\WhatsAppLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MerchantOfferService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'customer_request_id',
        'merchant_id',
        'price',
        'currency',
        'availability_status',
        'notes',
        'valid_until',
        'status',
    ];

    public function __construct(
        public MerchantContext $merchantContext,
        public RequestMatchingService $requestMatchingService,
        public MerchantPermissionService $merchantPermissionService,
        public MerchantOfferImageService $merchantOfferImageService,
        public ActivityLogService $activityLogService,
    ) {}

    public function currentOffer(CustomerRequest $customerRequest): ?MerchantOffer
    {
        $merchantId = $this->merchantContext->merchantId();

        if ($merchantId === null) {
            return null;
        }

        return MerchantOffer::query()
            ->where('customer_request_id', $customerRequest->id)
            ->where('merchant_id', $merchantId)
            ->with('images')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     */
    public function submit(CustomerRequest $customerRequest, array $data, array $images = []): MerchantOffer
    {
        $this->assertCan(PermissionKey::OffersCreate);
        $this->assertCanMutateOffer($customerRequest);

        $existing = $this->currentOffer($customerRequest);

        if ($existing !== null && $existing->status === OfferStatus::Submitted) {
            throw ValidationException::withMessages([
                'price' => 'This merchant already has an offer for this request.',
            ]);
        }

        $payload = $this->normalizedPayload($data);

        return DB::transaction(function () use ($customerRequest, $payload, $images, $existing) {
            $offer = $existing ?? new MerchantOffer;
            $isNew = ! $offer->exists;

            if ($isNew) {
                $offer->public_id = (string) Str::ulid();
                $offer->customer_request_id = $customerRequest->id;
                $offer->merchant_id = (int) $this->merchantContext->merchantId();
            }

            $offer->fill($payload);
            $offer->currency = MerchantOffer::CURRENCY;
            $offer->status = OfferStatus::Submitted;
            $offer->submitted_at = now();
            $offer->withdrawn_at = null;
            $offer->save();

            if ($images !== []) {
                $this->merchantOfferImageService->storeMany($offer, $images);
            }

            $this->activityLogService->recordCreated(
                subject: $offer,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $offer->public_id,
                metadata: [
                    'action' => $isNew ? 'merchant.offer_created' : 'merchant.offer_updated',
                    'customer_request_id' => $customerRequest->id,
                    'merchant_id' => $offer->merchant_id,
                ],
            );

            return $offer->fresh('images');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     * @param  list<int>  $removeImageIds
     */
    public function update(CustomerRequest $customerRequest, array $data, array $images = [], array $removeImageIds = []): MerchantOffer
    {
        $this->assertCan(PermissionKey::OffersUpdate);
        $this->assertCanMutateOffer($customerRequest);

        $offer = $this->requireOwnSubmittedOffer($customerRequest);
        $payload = $this->normalizedPayload($data);

        return DB::transaction(function () use ($offer, $payload, $images, $removeImageIds) {
            $originalValues = $offer->only(self::ACTIVITY_FIELDS);

            $this->merchantOfferImageService->deleteByIds($offer, $removeImageIds);
            $offer->unsetRelation('images');

            $remaining = $offer->images()->count();
            $incoming = count($images);

            if ($remaining + $incoming > MerchantOfferImageService::MAX_IMAGES) {
                throw ValidationException::withMessages([
                    'images' => 'An offer may have at most '.MerchantOfferImageService::MAX_IMAGES.' images.',
                ]);
            }

            $offer->fill($payload);
            $offer->currency = MerchantOffer::CURRENCY;
            $offer->save();

            if ($images !== []) {
                $this->merchantOfferImageService->storeMany($offer, $images, $remaining);
            }

            $this->activityLogService->recordChanges(
                subject: $offer,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $offer->public_id,
                metadata: [
                    'action' => 'merchant.offer_updated',
                    'customer_request_id' => $offer->customer_request_id,
                    'merchant_id' => $offer->merchant_id,
                ],
            );

            return $offer->fresh('images');
        });
    }

    public function withdraw(CustomerRequest $customerRequest): MerchantOffer
    {
        $this->assertCan(PermissionKey::OffersWithdraw);
        $this->assertActiveMerchantContext();

        $offer = $this->requireOwnSubmittedOffer($customerRequest);

        $originalValues = $offer->only(self::ACTIVITY_FIELDS);
        $offer->status = OfferStatus::Withdrawn;
        $offer->withdrawn_at = now();
        $offer->save();

        $this->activityLogService->recordChanges(
            subject: $offer,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $offer->public_id,
            metadata: [
                'action' => 'merchant.offer_withdrawn',
                'customer_request_id' => $offer->customer_request_id,
                'merchant_id' => $offer->merchant_id,
            ],
        );

        return $offer->fresh('images');
    }

    public function invalidateSubmittedOffersMissingMatch(CustomerRequest $customerRequest): int
    {
        $matchedMerchantIds = RequestMatch::query()
            ->where('customer_request_id', $customerRequest->id)
            ->pluck('merchant_id')
            ->all();

        $query = MerchantOffer::query()
            ->where('customer_request_id', $customerRequest->id)
            ->where('status', OfferStatus::Submitted);

        if ($matchedMerchantIds !== []) {
            $query->whereNotIn('merchant_id', $matchedMerchantIds);
        }

        $offers = $query->get();

        foreach ($offers as $offer) {
            $this->markInvalidated($offer);
        }

        return $offers->count();
    }

    public function invalidateSubmittedOfferIfUnmatched(int $customerRequestId, int $merchantId): void
    {
        $stillMatched = RequestMatch::query()
            ->where('customer_request_id', $customerRequestId)
            ->where('merchant_id', $merchantId)
            ->exists();

        if ($stillMatched) {
            return;
        }

        $offer = MerchantOffer::query()
            ->where('customer_request_id', $customerRequestId)
            ->where('merchant_id', $merchantId)
            ->where('status', OfferStatus::Submitted)
            ->first();

        if ($offer !== null) {
            $this->markInvalidated($offer);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForMerchant(?MerchantOffer $offer): ?array
    {
        if ($offer === null) {
            return null;
        }

        return [
            'public_id' => $offer->public_id,
            'price' => $offer->price,
            'currency' => $offer->currency,
            'availability_status' => $offer->availability_status?->value,
            'availability_status_formatted' => $offer->availability_status_formatted,
            'notes' => $offer->notes,
            'valid_until' => $offer->valid_until?->toDateString(),
            'status' => $offer->status?->value,
            'status_formatted' => $offer->status_formatted,
            'submitted_at' => $offer->submitted_at,
            'withdrawn_at' => $offer->withdrawn_at,
            'images' => $offer->images->map(fn (MerchantOfferImage $image) => [
                'id' => $image->id,
                'original_name' => $image->original_name,
                'url' => route('merchant.offers.images.show', [$offer, $image]),
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentSubmittedForCustomer(CustomerRequest $customerRequest): array
    {
        return MerchantOffer::query()
            ->submitted()
            ->where('customer_request_id', $customerRequest->id)
            ->with([
                'merchant:id,name,phone',
                'merchant.categoryAssignments' => fn ($query) => $query
                    ->where('category_id', $customerRequest->category_id)
                    ->select(['id', 'merchant_id', 'category_id', 'whatsapp_phone']),
                'images',
            ])
            ->latest('submitted_at')
            ->latest('id')
            ->get()
            ->map(fn (MerchantOffer $offer) => [
                'public_id' => $offer->public_id,
                'merchant_name' => $offer->merchant?->name,
                'price' => $offer->price,
                'currency' => $offer->currency,
                'availability_status_formatted' => $offer->availability_status_formatted,
                'notes' => $offer->notes,
                'valid_until' => $offer->valid_until?->toDateString(),
                'submitted_at' => $offer->submitted_at,
                'whatsapp_url' => $this->customerWhatsAppUrl($customerRequest, $offer),
                'images' => $offer->images->map(fn (MerchantOfferImage $image) => [
                    'id' => $image->id,
                    'url' => route('customer.offers.images.show', [$offer, $image]),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentForAdmin(CustomerRequest $customerRequest): array
    {
        return MerchantOffer::query()
            ->where('customer_request_id', $customerRequest->id)
            ->with(['merchant:id,name', 'images'])
            ->latest('submitted_at')
            ->latest('id')
            ->get()
            ->map(fn (MerchantOffer $offer) => [
                'public_id' => $offer->public_id,
                'merchant_name' => $offer->merchant?->name,
                'price' => $offer->price,
                'currency' => $offer->currency,
                'availability_status_formatted' => $offer->availability_status_formatted,
                'notes' => $offer->notes,
                'valid_until' => $offer->valid_until?->toDateString(),
                'status_formatted' => $offer->status_formatted,
                'submitted_at' => $offer->submitted_at,
                'images' => $offer->images->map(fn (MerchantOfferImage $image) => [
                    'id' => $image->id,
                    'url' => route('customer-requests.offers.images.show', [$customerRequest, $offer, $image]),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function markInvalidated(MerchantOffer $offer): void
    {
        $originalValues = $offer->only(self::ACTIVITY_FIELDS);
        $offer->status = OfferStatus::Invalidated;
        $offer->save();

        $this->activityLogService->recordChanges(
            subject: $offer,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $offer->public_id,
            metadata: [
                'action' => 'merchant.offer_invalidated',
                'customer_request_id' => $offer->customer_request_id,
                'merchant_id' => $offer->merchant_id,
            ],
        );
    }

    private function assertCan(PermissionKey $permission): void
    {
        $this->assertActiveMerchantContext();

        if (! $this->merchantPermissionService->currentCan($permission->value)) {
            abort(403);
        }
    }

    private function assertActiveMerchantContext(): void
    {
        if (! $this->merchantContext->isActive()) {
            abort(403);
        }
    }

    private function assertCanMutateOffer(CustomerRequest $customerRequest): void
    {
        $this->assertActiveMerchantContext();

        if ($customerRequest->status === RequestStatus::Closed || $customerRequest->status === RequestStatus::Cancelled) {
            throw ValidationException::withMessages([
                'price' => 'Offers cannot be submitted for closed or cancelled requests.',
            ]);
        }

        $match = $this->requestMatchingService->currentMerchantMatch($customerRequest);

        if ($match === null || ! $match->isVisibleToMerchant()) {
            abort(403);
        }
    }

    private function requireOwnSubmittedOffer(CustomerRequest $customerRequest): MerchantOffer
    {
        $offer = $this->currentOffer($customerRequest);

        if ($offer === null || $offer->merchant_id !== $this->merchantContext->merchantId()) {
            abort(404);
        }

        if ($offer->status !== OfferStatus::Submitted) {
            throw ValidationException::withMessages([
                'price' => 'Only a submitted offer can be updated or withdrawn.',
            ]);
        }

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $data): array
    {
        unset($data['merchant_id'], $data['customer_request_id'], $data['user_id'], $data['currency'], $data['public_id'], $data['status']);

        $availability = $data['availability_status'] ?? null;
        if (! $availability instanceof AvailabilityStatus) {
            $availability = AvailabilityStatus::tryFrom((int) $availability);
        }

        if ($availability === null) {
            throw ValidationException::withMessages([
                'availability_status' => 'The selected availability is invalid.',
            ]);
        }

        $validUntil = $data['valid_until'] ?? null;
        if ($validUntil === '') {
            $validUntil = null;
        }

        if ($validUntil !== null && $validUntil < now()->toDateString()) {
            throw ValidationException::withMessages([
                'valid_until' => 'The valid until date must not be in the past.',
            ]);
        }

        return [
            'price' => $data['price'],
            'availability_status' => $availability,
            'notes' => $data['notes'] ?? null,
            'valid_until' => $validUntil,
        ];
    }

    private function customerWhatsAppUrl(CustomerRequest $customerRequest, MerchantOffer $offer): ?string
    {
        $message = $this->customerWhatsAppMessage($customerRequest, $offer);
        $activityPhone = $offer->merchant?->categoryAssignments
            ->firstWhere('category_id', $customerRequest->category_id)
            ?->whatsapp_phone;

        foreach ([$activityPhone, $offer->merchant?->phone] as $phone) {
            $url = WhatsAppLink::url($phone, $message);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function customerWhatsAppMessage(CustomerRequest $customerRequest, MerchantOffer $offer): string
    {
        $reference = (string) $customerRequest->public_id;
        $price = (string) $offer->price;

        if (str_starts_with(strtolower((string) app()->getLocale()), 'ar')) {
            return "مرحبًا، أنا مهتم بالعرض المقدم على طلبي رقم {$reference} بقيمة {$price} OMR.";
        }

        return "Hello, I'm interested in your offer for request {$reference} priced at {$price} OMR.";
    }
}
