<?php

namespace App\Http\Controllers;

use App\Enums\CustomerExtraRequests\TransactionSource;
use App\Enums\CustomerExtraRequests\TransactionType;
use App\Http\Requests\CustomerExtraRequestAddRequest;
use App\Http\Requests\CustomerExtraRequestBulkAddRequest;
use App\Http\Requests\CustomerExtraRequestDeductRequest;
use App\Models\Customer;
use App\Services\CustomerExtraRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerExtraRequestController extends Controller
{
    public function __construct(
        public CustomerExtraRequestService $customerExtraRequestService,
    ) {}

    public function index(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $filters = [
            'type' => $request->input('type'),
            'source' => $request->input('source'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $transactions = $this->customerExtraRequestService->paginatedLedger($customer, $filters);
        $transactions->getCollection()->transform(fn ($transaction) => $this->presentTransaction($transaction));

        return Inertia::render('Customers/CustomerExtraRequestsPage', [
            'customer' => [
                'id' => $customer->id,
                'public_id' => $customer->public_id,
                'name' => $customer->display_name,
            ],
            'balance' => $this->customerExtraRequestService->balance((int) $customer->id),
            'transactions' => $transactions,
            'sources' => TransactionSource::manualChoicesToArray(),
            'filterSources' => TransactionSource::toArray(),
            'filterTypes' => TransactionType::toArray(),
            'filters' => [
                'type' => $filters['type'] !== null && $filters['type'] !== '' ? (int) $filters['type'] : null,
                'source' => $filters['source'] !== null && $filters['source'] !== '' ? (int) $filters['source'] : null,
                'date_from' => $filters['date_from'] ?: null,
                'date_to' => $filters['date_to'] ?: null,
            ],
        ]);
    }

    public function store(CustomerExtraRequestAddRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $source = TransactionSource::from((int) $data['source']);

        $this->customerExtraRequestService->addCredits(
            customer: $customer,
            amount: (int) $data['amount'],
            source: $source,
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            actor: $request->user(),
            paidAmount: $data['paid_amount'] ?? null,
        );

        return redirect()
            ->route('customers.extra-requests.index', $customer)
            ->with('success', 'تم إضافة الطلبات الإضافية بنجاح');
    }

    public function deduct(CustomerExtraRequestDeductRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $source = TransactionSource::from((int) $data['source']);

        $this->customerExtraRequestService->deductCredits(
            customer: $customer,
            amount: (int) $data['amount'],
            source: $source,
            notes: (string) $data['notes'],
            reference: $data['reference'] ?? null,
            actor: $request->user(),
        );

        return redirect()
            ->route('customers.extra-requests.index', $customer)
            ->with('success', 'تم خصم الطلبات الإضافية بنجاح');
    }

    public function bulkStore(CustomerExtraRequestBulkAddRequest $request)
    {
        $data = $request->validated();
        $source = TransactionSource::from((int) $data['source']);

        $customerIds = Customer::query()
            ->whereIn('public_id', $data['customer_public_ids'])
            ->pluck('id')
            ->all();

        $result = $this->customerExtraRequestService->bulkAdd(
            customerIds: $customerIds,
            amount: (int) $data['amount'],
            source: $source,
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            actor: $request->user(),
            paidAmount: $data['paid_amount'] ?? null,
        );

        return redirect()
            ->back()
            ->with('success', $this->bulkSuccessMessage($result['amount'], $result['customer_count']));
    }

    private function bulkSuccessMessage(int $amount, int $count): string
    {
        if (str_starts_with(strtolower((string) app()->getLocale()), 'ar')) {
            return "تمت إضافة {$amount} طلبًا إضافيًا إلى {$count} عميلًا بنجاح.";
        }

        return "{$amount} extra request credits were added to {$count} customers successfully.";
    }

    private function presentTransaction($transaction)
    {
        $createdBy = $transaction->createdBy;
        $transaction->setAttribute('created_by', $createdBy === null ? null : [
            'id' => $createdBy->id,
            'name' => $createdBy->name,
            'email' => $createdBy->email,
        ]);
        $transaction->unsetRelation('createdBy');

        $payment = $transaction->paymentTransaction;
        $transaction->setAttribute('payment', $payment === null ? null : [
            'public_id' => $payment->public_id,
            'amount' => $payment->amount,
        ]);
        $transaction->unsetRelation('paymentTransaction');

        return $transaction;
    }
}
