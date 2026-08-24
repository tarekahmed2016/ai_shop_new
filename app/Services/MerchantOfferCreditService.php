<?php

namespace App\Services;

use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\MerchantOfferCredits\TransactionType;
use App\Models\CustomerRequest;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCreditTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantOfferCreditService
{
    public function __construct(
        public PlatformSettingService $platformSettingService,
    ) {}

    public function isEnforcementEnabled(): bool
    {
        return $this->platformSettingService->isOfferCreditEnforcementEnabled();
    }

    public function setEnforcementEnabled(bool $enabled): void
    {
        $this->platformSettingService->setOfferCreditEnforcementEnabled($enabled);
    }

    public function maxManualAmount(): int
    {
        return max(1, (int) config('merchant_credits.max_manual_amount', 10000));
    }

    public function maxBulkMerchants(): int
    {
        return max(1, (int) config('merchant_credits.max_bulk_merchants', 100));
    }

    /**
     * Store paid amounts as 3-decimal strings. Null/blank stay null.
     */
    public function normalizePaidAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'paid_amount' => 'The paid amount must be a valid number.',
            ]);
        }

        $normalized = bcadd((string) $value, '0', 3);

        if (bccomp($normalized, '0', 3) < 0) {
            throw ValidationException::withMessages([
                'paid_amount' => 'The paid amount cannot be negative.',
            ]);
        }

        return $normalized;
    }

    public function formatPaidAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return bcadd((string) $value, '0', 3);
    }

    public function balance(int $merchantId): int
    {
        return (int) MerchantOfferCreditTransaction::query()
            ->where('merchant_id', $merchantId)
            ->sum('amount');
    }

    public function hasConsumedForRequest(int $merchantId, int $customerRequestId): bool
    {
        return MerchantOfferCreditTransaction::query()
            ->where('merchant_id', $merchantId)
            ->where('customer_request_id', $customerRequestId)
            ->where('type', TransactionType::OfferSubmit)
            ->exists();
    }

    /**
     * Snapshot for merchant-facing UI. Does not load the full ledger.
     *
     * @return array{enforcement_enabled: bool, balance: int, already_consumed: bool, can_consume_new: bool}
     */
    public function presentForMerchant(?int $merchantId, ?int $customerRequestId = null): array
    {
        $enforcement = $this->isEnforcementEnabled();
        $balance = $merchantId === null ? 0 : $this->balance($merchantId);
        $alreadyConsumed = $merchantId !== null && $customerRequestId !== null
            ? $this->hasConsumedForRequest($merchantId, $customerRequestId)
            : false;

        return [
            'enforcement_enabled' => $enforcement,
            'balance' => $balance,
            'already_consumed' => $alreadyConsumed,
            'can_consume_new' => ! $enforcement || $alreadyConsumed || $balance > 0,
        ];
    }

    /**
     * Must run inside a DB transaction after the merchant row is locked.
     */
    public function assertCanConsumeForSubmit(Merchant $merchant, CustomerRequest $customerRequest): void
    {
        if (! $this->isEnforcementEnabled()) {
            return;
        }

        if ($this->hasConsumedForRequest((int) $merchant->id, (int) $customerRequest->id)) {
            return;
        }

        if ($this->balance((int) $merchant->id) < 1) {
            throw ValidationException::withMessages([
                'credits' => $this->insufficientCreditsMessage(),
            ]);
        }
    }

    /**
     * Deduct one credit for a first-time submission when enforcement is on.
     * Idempotent per merchant + customer request. Must run in the offer transaction.
     */
    public function consumeForOfferSubmit(
        Merchant $merchant,
        CustomerRequest $customerRequest,
        MerchantOffer $offer,
        ?User $actor = null,
    ): bool {
        if (! $this->isEnforcementEnabled()) {
            return false;
        }

        if ($this->hasConsumedForRequest((int) $merchant->id, (int) $customerRequest->id)) {
            return false;
        }

        if ($this->balance((int) $merchant->id) < 1) {
            throw ValidationException::withMessages([
                'credits' => $this->insufficientCreditsMessage(),
            ]);
        }

        try {
            $previous = $this->balance((int) $merchant->id);

            MerchantOfferCreditTransaction::query()->create([
                'merchant_id' => $merchant->id,
                'type' => TransactionType::OfferSubmit,
                'source' => TransactionSource::OfferSubmit,
                'amount' => -1,
                'paid_amount' => null,
                'balance_after' => $previous - 1,
                'reference' => null,
                'notes' => null,
                'created_by_user_id' => $actor?->id,
                'customer_request_id' => $customerRequest->id,
                'merchant_offer_id' => $offer->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($this->balance((int) $merchant->id) < 0) {
            throw ValidationException::withMessages([
                'credits' => $this->insufficientCreditsMessage(),
            ]);
        }

        return true;
    }

    public function addCredits(
        Merchant $merchant,
        int $amount,
        TransactionSource $source,
        ?string $reference,
        ?string $notes,
        User $actor,
        TransactionType $type = TransactionType::ManualAdd,
        mixed $paidAmount = null,
    ): MerchantOfferCreditTransaction {
        $this->assertPositiveAmount($amount);
        $this->assertManualSource($source);

        if ($type === TransactionType::PromotionalBonus || $source === TransactionSource::PromotionalBonus) {
            $type = TransactionType::PromotionalBonus;
            $source = TransactionSource::PromotionalBonus;
        }

        $normalizedPaidAmount = $this->normalizePaidAmount($paidAmount);

        return DB::transaction(function () use ($merchant, $amount, $source, $reference, $notes, $actor, $type, $normalizedPaidAmount) {
            $this->lockMerchant((int) $merchant->id);

            return $this->recordAddition(
                merchant: $merchant,
                amount: $amount,
                source: $source,
                reference: $reference,
                notes: $notes,
                actor: $actor,
                type: $type,
                paidAmount: $normalizedPaidAmount,
            );
        });
    }

    public function deductCredits(
        Merchant $merchant,
        int $amount,
        TransactionSource $source,
        string $notes,
        ?string $reference,
        User $actor,
    ): MerchantOfferCreditTransaction {
        $this->assertPositiveAmount($amount);
        $this->assertManualSource($source);

        $trimmedNotes = trim($notes);
        if ($trimmedNotes === '') {
            throw ValidationException::withMessages([
                'notes' => 'A reason is required when deducting offer credits.',
            ]);
        }

        return DB::transaction(function () use ($merchant, $amount, $source, $trimmedNotes, $reference, $actor) {
            $this->lockMerchant((int) $merchant->id);

            $balance = $this->balance((int) $merchant->id);
            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Manual deduction cannot reduce the offer credit balance below zero.',
                ]);
            }

            return MerchantOfferCreditTransaction::query()->create([
                'merchant_id' => $merchant->id,
                'type' => TransactionType::ManualDeduct,
                'source' => $source,
                'amount' => -1 * $amount,
                'paid_amount' => null,
                'balance_after' => $balance - $amount,
                'reference' => $reference,
                'notes' => $trimmedNotes,
                'created_by_user_id' => $actor->id,
                'customer_request_id' => null,
                'merchant_offer_id' => null,
            ]);
        });
    }

    /**
     * @param  list<int>  $merchantIds
     * @return array{amount: int, merchant_count: int}
     */
    public function bulkAdd(
        array $merchantIds,
        int $amount,
        TransactionSource $source,
        ?string $reference,
        ?string $notes,
        User $actor,
        mixed $paidAmount = null,
    ): array {
        $this->assertPositiveAmount($amount);
        $this->assertManualSource($source);

        $ids = array_values(array_unique(array_map('intval', $merchantIds)));
        $ids = array_values(array_filter($ids, fn (int $id) => $id > 0));
        sort($ids);

        if ($ids === []) {
            throw ValidationException::withMessages([
                'merchant_public_ids' => 'Select at least one merchant.',
            ]);
        }

        if (count($ids) > $this->maxBulkMerchants()) {
            throw ValidationException::withMessages([
                'merchant_public_ids' => 'Too many merchants selected for one bulk credit operation.',
            ]);
        }

        $type = $source === TransactionSource::PromotionalBonus
            ? TransactionType::PromotionalBonus
            : TransactionType::BulkManualAdd;

        $normalizedPaidAmount = $this->normalizePaidAmount($paidAmount);

        return DB::transaction(function () use ($ids, $amount, $source, $reference, $notes, $actor, $type, $normalizedPaidAmount) {
            $merchants = Merchant::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($merchants->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'merchant_public_ids' => 'One or more selected merchants could not be processed.',
                ]);
            }

            foreach ($ids as $id) {
                $this->recordAddition(
                    merchant: $merchants[$id],
                    amount: $amount,
                    source: $source,
                    reference: $reference,
                    notes: $notes,
                    actor: $actor,
                    type: $type,
                    paidAmount: $normalizedPaidAmount,
                );
            }

            return [
                'amount' => $amount,
                'merchant_count' => count($ids),
            ];
        });
    }

    /**
     * @param  array{type?: mixed, source?: mixed, date_from?: mixed, date_to?: mixed}  $filters
     * @return LengthAwarePaginator<int, MerchantOfferCreditTransaction>
     */
    public function paginatedLedger(Merchant $merchant, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = $perPage > 0 ? $perPage : 25;

        return MerchantOfferCreditTransaction::query()
            ->where('merchant_id', $merchant->id)
            ->with(['createdBy:id,name,email'])
            ->when($this->integerFilter($filters['type'] ?? null), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($this->integerFilter($filters['source'] ?? null), function ($query, $source) {
                $query->where('source', $source);
            })
            ->when($this->dateFilter($filters['date_from'] ?? null), function ($query, $from) {
                $query->whereDate('created_at', '>=', $from);
            })
            ->when($this->dateFilter($filters['date_to'] ?? null), function ($query, $to) {
                $query->whereDate('created_at', '<=', $to);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{merchant?: mixed, type?: mixed, source?: mixed, date_from?: mixed, date_to?: mixed, paid_only?: mixed}  $filters
     * @return LengthAwarePaginator<int, MerchantOfferCreditTransaction>
     */
    public function paginatedGlobalLedger(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = $perPage > 0 ? $perPage : 25;

        return $this->globalLedgerQuery($filters)
            ->with([
                'merchant:id,public_id,name',
                'createdBy:id,name,email',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{merchant?: mixed, type?: mixed, source?: mixed, date_from?: mixed, date_to?: mixed, paid_only?: mixed}  $filters
     * @return array{total_paid_amount: string, credits_added: int}
     */
    public function globalLedgerSummary(array $filters = []): array
    {
        $paid = $this->globalLedgerQuery($filters)->sum('paid_amount');
        $creditsAdded = $this->globalLedgerQuery($filters)
            ->where('amount', '>', 0)
            ->sum('amount');

        return [
            'total_paid_amount' => $this->formatPaidAmount($paid === null ? '0' : (string) $paid) ?? '0.000',
            'credits_added' => (int) $creditsAdded,
        ];
    }

    /**
     * @param  array{merchant?: mixed, type?: mixed, source?: mixed, date_from?: mixed, date_to?: mixed, paid_only?: mixed}  $filters
     * @return Builder<MerchantOfferCreditTransaction>
     */
    private function globalLedgerQuery(array $filters): Builder
    {
        $merchantId = $this->merchantPublicIdFilter($filters['merchant'] ?? null);

        return MerchantOfferCreditTransaction::query()
            ->when($merchantId !== null, function ($query) use ($merchantId) {
                $query->where('merchant_id', $merchantId);
            })
            ->when($this->integerFilter($filters['type'] ?? null), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($this->integerFilter($filters['source'] ?? null), function ($query, $source) {
                $query->where('source', $source);
            })
            ->when($this->dateFilter($filters['date_from'] ?? null), function ($query, $from) {
                $query->whereDate('created_at', '>=', $from);
            })
            ->when($this->dateFilter($filters['date_to'] ?? null), function ($query, $to) {
                $query->whereDate('created_at', '<=', $to);
            })
            ->when($this->truthyFilter($filters['paid_only'] ?? null), function ($query) {
                $query->whereNotNull('paid_amount');
            });
    }

    private function merchantPublicIdFilter(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = Merchant::query()->where('public_id', (string) $value)->value('id');

        return $id === null ? 0 : (int) $id;
    }

    private function truthyFilter(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private function integerFilter(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function dateFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    public function lockMerchant(int $merchantId): Merchant
    {
        $merchant = Merchant::query()->whereKey($merchantId)->lockForUpdate()->first();

        if ($merchant === null) {
            throw ValidationException::withMessages([
                'merchant' => 'The selected merchant could not be processed.',
            ]);
        }

        return $merchant;
    }

    private function recordAddition(
        Merchant $merchant,
        int $amount,
        TransactionSource $source,
        ?string $reference,
        ?string $notes,
        User $actor,
        TransactionType $type,
        ?string $paidAmount = null,
    ): MerchantOfferCreditTransaction {
        $previous = $this->balance((int) $merchant->id);

        return MerchantOfferCreditTransaction::query()->create([
            'merchant_id' => $merchant->id,
            'type' => $type,
            'source' => $source,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'balance_after' => $previous + $amount,
            'reference' => $reference,
            'notes' => $notes,
            'created_by_user_id' => $actor->id,
            'customer_request_id' => null,
            'merchant_offer_id' => null,
        ]);
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'The credit amount must be a positive integer.',
            ]);
        }

        if ($amount > $this->maxManualAmount()) {
            throw ValidationException::withMessages([
                'amount' => 'The credit amount exceeds the allowed maximum.',
            ]);
        }
    }

    private function assertManualSource(TransactionSource $source): void
    {
        if (! in_array($source, TransactionSource::manualChoices(), true)) {
            throw ValidationException::withMessages([
                'source' => 'The selected source is invalid.',
            ]);
        }
    }

    private function insufficientCreditsMessage(): string
    {
        if (str_starts_with(strtolower((string) app()->getLocale()), 'ar')) {
            return 'لا يوجد لديك رصيد كافٍ لتقديم عرض. تواصل معنا أو قم بشحن رصيد العروض.';
        }

        return 'You do not have enough offer credits to submit an offer.';
    }
}
