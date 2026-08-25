<?php

namespace App\Services;

use App\Enums\Payments\Method;
use App\Enums\Payments\Status;
use App\Enums\Payments\Type;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentTransactionService
{
    /**
     * Store OMR amounts as 3-decimal strings. Null/blank stay null. Zero becomes null.
     */
    public function normalizeAmount(mixed $value, string $field = 'paid_amount'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => 'The paid amount must be a valid number.',
            ]);
        }

        $normalized = bcadd((string) $value, '0', 3);

        if (bccomp($normalized, '0', 3) < 0) {
            throw ValidationException::withMessages([
                $field => 'The paid amount cannot be negative.',
            ]);
        }

        if (bccomp($normalized, '0', 3) === 0) {
            return null;
        }

        return $normalized;
    }

    public function formatAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return bcadd((string) $value, '0', 3);
    }

    public function recordPaid(
        User $payer,
        Type $type,
        string $amount,
        Method $method,
        ?User $actor,
        ?string $reference,
        ?string $notes,
        ?Customer $relatedCustomer = null,
        ?Merchant $relatedMerchant = null,
        mixed $paidAt = null,
    ): PaymentTransaction {
        $normalized = $this->normalizeAmount($amount, 'paid_amount');

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'paid_amount' => 'A paid amount greater than zero is required.',
            ]);
        }

        return DB::transaction(function () use (
            $payer,
            $type,
            $normalized,
            $method,
            $actor,
            $reference,
            $notes,
            $relatedCustomer,
            $relatedMerchant,
            $paidAt,
        ) {
            $payment = PaymentTransaction::query()->create([
                'payer_user_id' => $payer->id,
                'type' => $type,
                'amount' => $normalized,
                'status' => Status::Paid,
                'payment_method' => $method,
                'reference' => $reference,
                'notes' => $notes,
                'paid_at' => $paidAt ?? now(),
                'created_by_user_id' => $actor?->id,
                'related_customer_id' => $relatedCustomer?->id,
                'related_merchant_id' => $relatedMerchant?->id,
            ]);

            app(MarketerCommissionService::class)->createForPaidPayment($payment);

            return $payment;
        });
    }

    /**
     * Canonical payer for a Merchant: the earliest Owner membership User.
     * Staff is never used. Paid records are rejected when no owner exists.
     */
    public function canonicalMerchantPayer(Merchant $merchant): User
    {
        $merchant->loadMissing('ownerMembership.user');
        $owner = $merchant->ownerMembership?->user;

        if ($owner === null) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Paid merchant offer credits require a merchant owner user as the payer.',
            ]);
        }

        return $owner;
    }

    public function canonicalCustomerPayer(Customer $customer): User
    {
        $customer->loadMissing('user');
        $user = $customer->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'paid_amount' => 'Paid extra requests require a linked customer user account.',
            ]);
        }

        return $user;
    }

    /**
     * @param  array{payer?: mixed, type?: mixed, method?: mixed, status?: mixed, date_from?: mixed, date_to?: mixed}  $filters
     * @return LengthAwarePaginator<int, PaymentTransaction>
     */
    public function paginatedAdminPayments(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = $perPage > 0 ? $perPage : 25;

        return $this->adminQuery($filters)
            ->with([
                'payer:id,name,email',
                'createdBy:id,name,email',
                'relatedCustomer:id,public_id,name',
                'relatedMerchant:id,public_id,name',
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, PaymentTransaction>
     */
    public function paginatedMarketerReferralPayments(Marketer $marketer, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = $perPage > 0 ? $perPage : 25;

        return $this->marketerReferralQuery($marketer)
            ->with([
                'payer:id,name',
                'relatedCustomer:id,public_id,name',
                'relatedMerchant:id,public_id,name',
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{total_amount: string, month_amount: string, paying_users: int}
     */
    public function marketerReferralSummary(Marketer $marketer, ?CarbonImmutable $now = null): array
    {
        $monthStart = ($now ?? CarbonImmutable::now())->startOfMonth();

        $total = $this->marketerReferralQuery($marketer)->sum('amount');
        $month = $this->marketerReferralQuery($marketer)
            ->where('paid_at', '>=', $monthStart)
            ->sum('amount');
        $payingUsers = $this->marketerReferralQuery($marketer)
            ->distinct()
            ->count('payer_user_id');

        return [
            'total_amount' => $this->formatAmount($total === null ? '0' : (string) $total) ?? '0.000',
            'month_amount' => $this->formatAmount($month === null ? '0' : (string) $month) ?? '0.000',
            'paying_users' => (int) $payingUsers,
        ];
    }

    /**
     * @return Builder<PaymentTransaction>
     */
    public function marketerReferralQuery(Marketer $marketer): Builder
    {
        return PaymentTransaction::query()
            ->where('status', Status::Paid)
            ->whereIn('payer_user_id', function ($query) use ($marketer) {
                $query->select('referred_user_id')
                    ->from('marketer_referrals')
                    ->where('marketer_id', $marketer->id);
            });
    }

    /**
     * @param  array{payer?: mixed, type?: mixed, method?: mixed, status?: mixed, date_from?: mixed, date_to?: mixed}  $filters
     * @return Builder<PaymentTransaction>
     */
    private function adminQuery(array $filters): Builder
    {
        return PaymentTransaction::query()
            ->when($this->payerFilter($filters['payer'] ?? null), function ($query, $payerId) {
                $query->where('payer_user_id', $payerId);
            })
            ->when($this->integerFilter($filters['type'] ?? null), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($this->integerFilter($filters['method'] ?? null), function ($query, $method) {
                $query->where('payment_method', $method);
            })
            ->when($this->integerFilter($filters['status'] ?? null), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($this->dateFilter($filters['date_from'] ?? null), function ($query, $from) {
                $query->whereDate('paid_at', '>=', $from);
            })
            ->when($this->dateFilter($filters['date_to'] ?? null), function ($query, $to) {
                $query->whereDate('paid_at', '<=', $to);
            });
    }

    private function payerFilter(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $needle = (string) $value;
        $id = User::query()
            ->where(function ($query) use ($needle) {
                $query->where('email', $needle)
                    ->orWhere('name', 'like', '%'.$needle.'%');
            })
            ->value('id');

        return $id === null ? 0 : (int) $id;
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
}
