<?php

namespace App\Services;

use App\Enums\MarketerCommissions\CommissionType;
use App\Enums\MarketerCommissions\Status as CommissionStatus;
use App\Enums\Payments\Method;
use App\Enums\Payments\Status as PaymentStatus;
use App\Enums\Payments\Type as PaymentType;
use App\Models\Marketer;
use App\Models\MarketerCommission;
use App\Models\MarketerPayout;
use App\Models\MarketerReferral;
use App\Models\PaymentTransaction;
use App\Models\PlatformSetting;
use App\Models\PlatformSettingChange;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketerCommissionService
{
    public function __construct(
        public PlatformSettingService $platformSettingService,
        public PaymentTransactionService $paymentTransactionService,
    ) {}

    /**
     * @return list<PaymentType>
     */
    public function eligiblePaymentTypes(): array
    {
        return [
            PaymentType::CustomerExtraRequests,
            PaymentType::MerchantOfferCredits,
        ];
    }

    public function createForPaidPayment(PaymentTransaction $payment): ?MarketerCommission
    {
        if ($payment->status !== PaymentStatus::Paid) {
            return null;
        }

        if (! in_array($payment->type, $this->eligiblePaymentTypes(), true)) {
            return null;
        }

        $existing = MarketerCommission::query()
            ->where('payment_transaction_id', $payment->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $referral = MarketerReferral::query()
            ->where('referred_user_id', $payment->payer_user_id)
            ->first();

        if ($referral === null) {
            return null;
        }

        $marketer = Marketer::query()->find($referral->marketer_id);
        if ($marketer === null) {
            return null;
        }

        $paymentAmount = $this->paymentTransactionService->formatAmount($payment->amount) ?? '0.000';
        $rate = $this->effectiveRate($marketer, $payment->type);
        $amount = $this->calculateCommissionAmount($paymentAmount, $rate);

        try {
            return MarketerCommission::query()->create([
                'marketer_id' => $marketer->id,
                'marketer_referral_id' => $referral->id,
                'payment_transaction_id' => $payment->id,
                'referred_user_id' => $payment->payer_user_id,
                'payment_type' => $payment->type,
                'payment_amount' => $paymentAmount,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => $rate,
                'commission_amount' => $amount,
                'status' => CommissionStatus::Approved,
                'earned_at' => $payment->paid_at ?? now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return MarketerCommission::query()
                ->where('payment_transaction_id', $payment->id)
                ->first();
        }
    }

    public function backfillExistingPaidPayments(): int
    {
        $created = 0;
        $types = array_map(fn (PaymentType $type) => $type->value, $this->eligiblePaymentTypes());

        PaymentTransaction::query()
            ->where('status', PaymentStatus::Paid)
            ->whereIn('type', $types)
            ->whereDoesntHave('commission')
            ->orderBy('id')
            ->chunkById(100, function ($payments) use (&$created): void {
                foreach ($payments as $payment) {
                    $commission = $this->createForPaidPayment($payment);
                    if ($commission !== null && $commission->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    public function calculateCommissionAmount(string $paymentAmount, string $rate): string
    {
        $product = bcmul($paymentAmount, $rate, 6);

        return bcdiv($product, '100', 3);
    }

    public function effectiveRate(Marketer $marketer, PaymentType $type): string
    {
        $override = $this->overrideRate($marketer, $type);

        if ($override !== null) {
            return $override;
        }

        return $this->globalRate($type);
    }

    public function overrideRate(Marketer $marketer, PaymentType $type): ?string
    {
        $raw = match ($type) {
            PaymentType::CustomerExtraRequests => $marketer->customer_commission_rate,
            PaymentType::MerchantOfferCredits => $marketer->merchant_commission_rate,
            default => null,
        };

        if ($raw === null || $raw === '') {
            return null;
        }

        return $this->normalizeRate($raw);
    }

    public function globalRate(PaymentType $type): string
    {
        $key = $this->settingKeyForType($type);
        $fallback = $this->defaultRateForType($type);
        $stored = $this->platformSettingService->get($key);

        if ($stored === null || $stored === '' || ! is_numeric($stored)) {
            return $this->normalizeRate($fallback);
        }

        return $this->normalizeRate($stored);
    }

    public function normalizeRate(mixed $value, string $field = 'rate'): string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                $field => 'A commission rate is required.',
            ]);
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => 'The commission rate must be a valid number.',
            ]);
        }

        $normalized = bcadd((string) $value, '0', 3);

        if (bccomp($normalized, '0', 3) < 0 || bccomp($normalized, '100', 3) > 0) {
            throw ValidationException::withMessages([
                $field => 'The commission rate must be between 0 and 100.',
            ]);
        }

        return $normalized;
    }

    public function nullableRate(mixed $value, string $field = 'rate'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeRate($value, $field);
    }

    /**
     * @return array{customer_extra_requests: string, merchant_offer_credits: string}
     */
    public function globalRates(): array
    {
        return [
            'customer_extra_requests' => $this->globalRate(PaymentType::CustomerExtraRequests),
            'merchant_offer_credits' => $this->globalRate(PaymentType::MerchantOfferCredits),
        ];
    }

    public function setGlobalRates(
        mixed $customerRate,
        mixed $merchantRate,
        ?User $actor,
        ?string $notes = null,
    ): void {
        $customer = $this->normalizeRate($customerRate, 'customer_commission_rate');
        $merchant = $this->normalizeRate($merchantRate, 'merchant_commission_rate');

        DB::transaction(function () use ($customer, $merchant, $actor, $notes): void {
            $this->setAuditedRate(
                PlatformSetting::KEY_MARKETER_COMMISSION_CUSTOMER,
                $customer,
                $actor,
                $notes,
            );
            $this->setAuditedRate(
                PlatformSetting::KEY_MARKETER_COMMISSION_MERCHANT,
                $merchant,
                $actor,
                $notes,
            );
        });
    }

    public function updateMarketerOverrides(
        Marketer $marketer,
        mixed $customerRate,
        mixed $merchantRate,
    ): Marketer {
        $marketer->customer_commission_rate = $this->nullableRate($customerRate, 'customer_commission_rate');
        $marketer->merchant_commission_rate = $this->nullableRate($merchantRate, 'merchant_commission_rate');
        $marketer->save();

        return $marketer->refresh();
    }

    /**
     * @return array{
     *     referral_payments: string,
     *     approved_commission: string,
     *     pending_commission: string,
     *     paid: string,
     *     outstanding: string
     * }
     */
    public function financialSummary(Marketer $marketer): array
    {
        $referral = $this->paymentTransactionService->marketerReferralSummary($marketer);
        $approved = $this->sumCommissions($marketer, CommissionStatus::Approved);
        $pending = $this->sumCommissions($marketer, CommissionStatus::Pending);
        $paid = $this->sumPayouts($marketer);
        $outstanding = bcsub($approved, $paid, 3);

        if (bccomp($outstanding, '0', 3) < 0) {
            $outstanding = '0.000';
        }

        return [
            'referral_payments' => $referral['total_amount'],
            'approved_commission' => $approved,
            'pending_commission' => $pending,
            'paid' => $paid,
            'outstanding' => $outstanding,
        ];
    }

    public function recordPayout(
        Marketer $marketer,
        mixed $amount,
        Method $method,
        ?User $actor,
        ?string $reference,
        ?string $notes,
        mixed $paidAt = null,
    ): MarketerPayout {
        $normalized = $this->paymentTransactionService->normalizeAmount($amount, 'amount');

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'amount' => 'A payout amount greater than zero is required.',
            ]);
        }

        return DB::transaction(function () use ($marketer, $normalized, $method, $actor, $reference, $notes, $paidAt) {
            $locked = Marketer::query()->whereKey($marketer->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'marketer' => 'The selected marketer could not be processed.',
                ]);
            }

            $summary = $this->financialSummary($locked);

            if (bccomp($normalized, $summary['outstanding'], 3) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The payout amount cannot exceed the outstanding balance.',
                ]);
            }

            return MarketerPayout::query()->create([
                'marketer_id' => $locked->id,
                'amount' => $normalized,
                'payment_method' => $method,
                'reference' => $this->nullableText($reference),
                'notes' => $this->nullableText($notes),
                'paid_at' => $paidAt ?? now(),
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }

    /**
     * @return LengthAwarePaginator<int, MarketerCommission>
     */
    public function paginatedCommissions(Marketer $marketer, int $perPage = 25): LengthAwarePaginator
    {
        return $this->commissionQuery($marketer)
            ->with(['referredUser:id,name'])
            ->orderByDesc('earned_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, MarketerPayout>
     */
    public function paginatedPayouts(Marketer $marketer, int $perPage = 25): LengthAwarePaginator
    {
        return MarketerPayout::query()
            ->where('marketer_id', $marketer->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, Marketer>
     */
    public function paginatedAdminSummaries(int $perPage = 25): LengthAwarePaginator
    {
        $approved = CommissionStatus::Approved->value;
        $pending = CommissionStatus::Pending->value;
        $paidPayment = PaymentStatus::Paid->value;

        return Marketer::query()
            ->with(['user:id,name,email'])
            ->select('marketers.*')
            ->selectSub(
                PaymentTransaction::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->where('status', $paidPayment)
                    ->whereIn('payer_user_id', MarketerReferral::query()
                        ->select('referred_user_id')
                        ->whereColumn('marketer_id', 'marketers.id')),
                'referral_payments_sum',
            )
            ->selectSub(
                MarketerCommission::query()
                    ->selectRaw('COALESCE(SUM(commission_amount), 0)')
                    ->whereColumn('marketer_id', 'marketers.id')
                    ->where('status', $approved),
                'approved_commission_sum',
            )
            ->selectSub(
                MarketerCommission::query()
                    ->selectRaw('COALESCE(SUM(commission_amount), 0)')
                    ->whereColumn('marketer_id', 'marketers.id')
                    ->where('status', $pending),
                'pending_commission_sum',
            )
            ->selectSub(
                MarketerPayout::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('marketer_id', 'marketers.id'),
                'paid_sum',
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPortalCommission(MarketerCommission $commission): array
    {
        return [
            'public_id' => $commission->public_id,
            'earned_at' => $commission->earned_at?->toIso8601String(),
            'referred_user_name' => $commission->referredUser?->name,
            'payment_type' => $commission->payment_type_formatted,
            'payment_amount' => $this->paymentTransactionService->formatAmount($commission->payment_amount),
            'commission_rate' => $this->normalizeRate($commission->commission_rate),
            'commission_amount' => $this->paymentTransactionService->formatAmount($commission->commission_amount),
            'status' => $commission->status_formatted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPortalPayout(MarketerPayout $payout): array
    {
        return [
            'public_id' => $payout->public_id,
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'amount' => $this->paymentTransactionService->formatAmount($payout->amount),
            'payment_method' => $payout->payment_method_formatted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentAdminPayout(MarketerPayout $payout): array
    {
        $createdBy = $payout->createdBy;

        return [
            'public_id' => $payout->public_id,
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'amount' => $this->paymentTransactionService->formatAmount($payout->amount),
            'payment_method' => $payout->payment_method_formatted,
            'reference' => $payout->reference,
            'notes' => $payout->notes,
            'created_by' => $createdBy === null ? null : [
                'id' => $createdBy->id,
                'name' => $createdBy->name,
                'email' => $createdBy->email,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentAdminSummary(Marketer $marketer): array
    {
        $approved = $this->paymentTransactionService->formatAmount($marketer->approved_commission_sum ?? '0') ?? '0.000';
        $paid = $this->paymentTransactionService->formatAmount($marketer->paid_sum ?? '0') ?? '0.000';
        $outstanding = bcsub($approved, $paid, 3);
        if (bccomp($outstanding, '0', 3) < 0) {
            $outstanding = '0.000';
        }

        return [
            'public_id' => $marketer->public_id,
            'name' => $marketer->user?->name,
            'email' => $marketer->user?->email,
            'referral_payments' => $this->paymentTransactionService->formatAmount($marketer->referral_payments_sum ?? '0') ?? '0.000',
            'approved_commission' => $approved,
            'pending_commission' => $this->paymentTransactionService->formatAmount($marketer->pending_commission_sum ?? '0') ?? '0.000',
            'paid' => $paid,
            'outstanding' => $outstanding,
        ];
    }

    /**
     * @return Builder<MarketerCommission>
     */
    private function commissionQuery(Marketer $marketer): Builder
    {
        return MarketerCommission::query()->where('marketer_id', $marketer->id);
    }

    private function sumCommissions(Marketer $marketer, CommissionStatus $status): string
    {
        $sum = $this->commissionQuery($marketer)
            ->where('status', $status)
            ->sum('commission_amount');

        return $this->paymentTransactionService->formatAmount($sum === null ? '0' : (string) $sum) ?? '0.000';
    }

    private function sumPayouts(Marketer $marketer): string
    {
        $sum = MarketerPayout::query()
            ->where('marketer_id', $marketer->id)
            ->sum('amount');

        return $this->paymentTransactionService->formatAmount($sum === null ? '0' : (string) $sum) ?? '0.000';
    }

    private function setAuditedRate(string $key, string $newValue, ?User $actor, ?string $notes): void
    {
        $old = $this->platformSettingService->get($key);
        $oldNormalized = ($old === null || $old === '' || ! is_numeric($old))
            ? $this->defaultRateForKey($key)
            : $this->normalizeRate($old);

        $this->platformSettingService->set($key, $newValue);

        if (bccomp($oldNormalized, $newValue, 3) === 0) {
            return;
        }

        PlatformSettingChange::query()->create([
            'key' => $key,
            'old_value' => $oldNormalized,
            'new_value' => $newValue,
            'notes' => $this->nullableText($notes),
            'changed_by_user_id' => $actor?->id,
        ]);
    }

    private function settingKeyForType(PaymentType $type): string
    {
        return match ($type) {
            PaymentType::CustomerExtraRequests => PlatformSetting::KEY_MARKETER_COMMISSION_CUSTOMER,
            PaymentType::MerchantOfferCredits => PlatformSetting::KEY_MARKETER_COMMISSION_MERCHANT,
            default => PlatformSetting::KEY_MARKETER_COMMISSION_CUSTOMER,
        };
    }

    private function defaultRateForType(PaymentType $type): string
    {
        return match ($type) {
            PaymentType::CustomerExtraRequests => (string) config('marketer_commissions.customer_extra_requests_rate', '10.000'),
            PaymentType::MerchantOfferCredits => (string) config('marketer_commissions.merchant_offer_credits_rate', '20.000'),
            default => '0.000',
        };
    }

    private function defaultRateForKey(string $key): string
    {
        return match ($key) {
            PlatformSetting::KEY_MARKETER_COMMISSION_CUSTOMER => $this->defaultRateForType(PaymentType::CustomerExtraRequests),
            PlatformSetting::KEY_MARKETER_COMMISSION_MERCHANT => $this->defaultRateForType(PaymentType::MerchantOfferCredits),
            default => '0.000',
        };
    }

    private function nullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
