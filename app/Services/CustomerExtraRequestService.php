<?php

namespace App\Services;

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Enums\CustomerExtraRequests\TransactionType;
use App\Enums\Payments\Method;
use App\Enums\Payments\Type as PaymentType;
use App\Models\Customer;
use App\Models\CustomerExtraRequestTransaction;
use App\Models\CustomerRequest;
use App\Models\User;
use App\Support\CustomerRequests\CustomerRequestMessages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerExtraRequestService
{
    public function __construct(
        public PaymentTransactionService $paymentTransactionService,
    ) {}

    public function maxManualAmount(): int
    {
        return max(1, (int) config('customer_extra_requests.max_manual_amount', 10000));
    }

    public function maxBulkCustomers(): int
    {
        return max(1, (int) config('customer_extra_requests.max_bulk_customers', 100));
    }

    public function balance(int $customerId): int
    {
        return (int) CustomerExtraRequestTransaction::query()
            ->where('customer_id', $customerId)
            ->sum('amount');
    }

    public function hasConsumedForRequest(int $customerRequestId): bool
    {
        return CustomerExtraRequestTransaction::query()
            ->where('customer_request_id', $customerRequestId)
            ->where('type', TransactionType::RequestCreate)
            ->exists();
    }

    /**
     * Consume one extra request credit for a newly created CustomerRequest.
     * Must run inside the request-creation transaction after the customer row is locked
     * and after daily free quota is already exhausted.
     */
    public function consumeForNewRequest(Customer $customer, CustomerRequest $customerRequest): bool
    {
        if ($this->hasConsumedForRequest((int) $customerRequest->id)) {
            return false;
        }

        if ($this->balance((int) $customer->id) < 1) {
            throw ValidationException::withMessages([
                'request_text' => CustomerRequestMessages::dailyLimitReached(),
            ]);
        }

        try {
            CustomerExtraRequestTransaction::query()->create([
                'customer_id' => $customer->id,
                'type' => TransactionType::RequestCreate,
                'amount' => -1,
                'source' => TransactionSource::RequestCreate,
                'payment_transaction_id' => null,
                'reference' => null,
                'notes' => null,
                'created_by_user_id' => null,
                'customer_request_id' => $customerRequest->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($this->balance((int) $customer->id) < 0) {
            throw ValidationException::withMessages([
                'request_text' => CustomerRequestMessages::dailyLimitReached(),
            ]);
        }

        return true;
    }

    public function addCredits(
        Customer $customer,
        int $amount,
        TransactionSource $source,
        ?string $reference,
        ?string $notes,
        User $actor,
        TransactionType $type = TransactionType::ManualAdd,
        mixed $paidAmount = null,
    ): CustomerExtraRequestTransaction {
        $this->assertPositiveAmount($amount);
        $this->assertManualSource($source);

        if ($type === TransactionType::PromotionalBonus || $source === TransactionSource::PromotionalBonus) {
            $type = TransactionType::PromotionalBonus;
            $source = TransactionSource::PromotionalBonus;
        }

        $normalizedPaidAmount = $this->paymentTransactionService->normalizeAmount($paidAmount);

        return DB::transaction(function () use ($customer, $amount, $source, $reference, $notes, $actor, $type, $normalizedPaidAmount) {
            $this->lockCustomer((int) $customer->id);

            return $this->recordAddition(
                customer: $customer,
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
        Customer $customer,
        int $amount,
        TransactionSource $source,
        string $notes,
        ?string $reference,
        User $actor,
    ): CustomerExtraRequestTransaction {
        $this->assertPositiveAmount($amount);
        $this->assertManualSource($source);

        $trimmedNotes = trim($notes);
        if ($trimmedNotes === '') {
            throw ValidationException::withMessages([
                'notes' => 'A reason is required when deducting extra request credits.',
            ]);
        }

        return DB::transaction(function () use ($customer, $amount, $source, $trimmedNotes, $reference, $actor) {
            $this->lockCustomer((int) $customer->id);

            $balance = $this->balance((int) $customer->id);
            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Manual deduction cannot reduce the extra request balance below zero.',
                ]);
            }

            return CustomerExtraRequestTransaction::query()->create([
                'customer_id' => $customer->id,
                'type' => TransactionType::ManualDeduct,
                'amount' => -1 * $amount,
                'source' => $source,
                'payment_transaction_id' => null,
                'reference' => $reference,
                'notes' => $trimmedNotes,
                'created_by_user_id' => $actor->id,
                'customer_request_id' => null,
            ]);
        });
    }

    /**
     * @param  list<int>  $customerIds
     * @return array{amount: int, customer_count: int}
     */
    public function bulkAdd(
        array $customerIds,
        int $amount,
        TransactionSource $source,
        ?string $reference,
        ?string $notes,
        User $actor,
        mixed $paidAmount = null,
    ): array {
        $this->assertPositiveAmount($amount);
        $this->assertManualSource($source);

        $ids = array_values(array_unique(array_map('intval', $customerIds)));
        $ids = array_values(array_filter($ids, fn (int $id) => $id > 0));
        sort($ids);

        if ($ids === []) {
            throw ValidationException::withMessages([
                'customer_public_ids' => 'Select at least one customer.',
            ]);
        }

        if (count($ids) > $this->maxBulkCustomers()) {
            throw ValidationException::withMessages([
                'customer_public_ids' => 'Too many customers selected for one bulk extra-request operation.',
            ]);
        }

        $type = $source === TransactionSource::PromotionalBonus
            ? TransactionType::PromotionalBonus
            : TransactionType::BulkManualAdd;

        $normalizedPaidAmount = $this->paymentTransactionService->normalizeAmount($paidAmount);

        return DB::transaction(function () use ($ids, $amount, $source, $reference, $notes, $actor, $type, $normalizedPaidAmount) {
            $customers = Customer::query()
                ->with('user')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($customers->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'customer_public_ids' => 'One or more selected customers could not be processed.',
                ]);
            }

            foreach ($ids as $id) {
                $this->recordAddition(
                    customer: $customers[$id],
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
                'customer_count' => count($ids),
            ];
        });
    }

    /**
     * @param  array{type?: mixed, source?: mixed, date_from?: mixed, date_to?: mixed}  $filters
     * @return LengthAwarePaginator<int, CustomerExtraRequestTransaction>
     */
    public function paginatedLedger(Customer $customer, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = $perPage > 0 ? $perPage : 25;

        return CustomerExtraRequestTransaction::query()
            ->where('customer_id', $customer->id)
            ->with([
                'createdBy:id,name,email',
                'paymentTransaction:id,public_id,amount,status',
            ])
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

    public function lockCustomer(int $customerId): Customer
    {
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->first();

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer' => 'The selected customer could not be processed.',
            ]);
        }

        return $customer;
    }

    private function recordAddition(
        Customer $customer,
        int $amount,
        TransactionSource $source,
        ?string $reference,
        ?string $notes,
        User $actor,
        TransactionType $type,
        ?string $paidAmount = null,
    ): CustomerExtraRequestTransaction {
        $paymentId = null;

        if ($paidAmount !== null) {
            $payer = $this->paymentTransactionService->canonicalCustomerPayer($customer);
            $payment = $this->paymentTransactionService->recordPaid(
                payer: $payer,
                type: PaymentType::CustomerExtraRequests,
                amount: $paidAmount,
                method: Method::fromExtraRequestSource($source),
                actor: $actor,
                reference: $reference,
                notes: $notes,
                relatedCustomer: $customer,
            );
            $paymentId = $payment->id;
        }

        return CustomerExtraRequestTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => $type,
            'amount' => $amount,
            'source' => $source,
            'payment_transaction_id' => $paymentId,
            'reference' => $reference,
            'notes' => $notes,
            'created_by_user_id' => $actor->id,
            'customer_request_id' => null,
        ]);
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'The extra request amount must be a positive integer.',
            ]);
        }

        if ($amount > $this->maxManualAmount()) {
            throw ValidationException::withMessages([
                'amount' => 'The extra request amount exceeds the allowed maximum.',
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
