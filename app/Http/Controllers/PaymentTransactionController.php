<?php

namespace App\Http\Controllers;

use App\Enums\Payments\Method;
use App\Enums\Payments\Status;
use App\Enums\Payments\Type;
use App\Models\PaymentTransaction;
use App\Services\PaymentTransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentTransactionController extends Controller
{
    public function __construct(
        public PaymentTransactionService $paymentTransactionService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentTransaction::class);

        $filters = [
            'payer' => $request->input('payer'),
            'type' => $request->input('type'),
            'method' => $request->input('method'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $payments = $this->paymentTransactionService->paginatedAdminPayments($filters);
        $payments->getCollection()->transform(fn ($payment) => $this->presentAdminPayment($payment));

        return Inertia::render('Payments/PaymentsPage', [
            'payments' => $payments,
            'types' => Type::toArray(),
            'methods' => Method::toArray(),
            'statuses' => Status::toArray(),
            'filters' => [
                'payer' => $filters['payer'] !== null && $filters['payer'] !== '' ? (string) $filters['payer'] : null,
                'type' => $filters['type'] !== null && $filters['type'] !== '' ? (int) $filters['type'] : null,
                'method' => $filters['method'] !== null && $filters['method'] !== '' ? (int) $filters['method'] : null,
                'status' => $filters['status'] !== null && $filters['status'] !== '' ? (int) $filters['status'] : null,
                'date_from' => $filters['date_from'] ?: null,
                'date_to' => $filters['date_to'] ?: null,
            ],
        ]);
    }

    public function show(PaymentTransaction $payment)
    {
        $this->authorize('view', $payment);

        $payment->load([
            'payer:id,name,email',
            'createdBy:id,name,email',
            'relatedCustomer:id,public_id,name',
            'relatedMerchant:id,public_id,name',
            'customerExtraRequestTransactions.customer:id,public_id,name',
            'merchantOfferCreditTransactions.merchant:id,public_id,name',
        ]);

        return Inertia::render('Payments/PaymentShowPage', [
            'payment' => $this->presentAdminPayment($payment, includeAudit: true),
        ]);
    }

    private function presentAdminPayment(PaymentTransaction $payment, bool $includeAudit = false): array
    {
        $payer = $payment->payer;
        $createdBy = $payment->createdBy;
        $customer = $payment->relatedCustomer;
        $merchant = $payment->relatedMerchant;

        $payload = [
            'id' => $payment->id,
            'public_id' => $payment->public_id,
            'amount' => $this->paymentTransactionService->formatAmount($payment->amount),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'type' => $payment->type_formatted,
            'status' => $payment->status_formatted,
            'payment_method' => $payment->payment_method_formatted,
            'payer' => $payer === null ? null : [
                'id' => $payer->id,
                'name' => $payer->name,
                'email' => $payer->email,
            ],
            'created_by' => $createdBy === null ? null : [
                'id' => $createdBy->id,
                'name' => $createdBy->name,
                'email' => $createdBy->email,
            ],
            'customer' => $customer === null ? null : [
                'public_id' => $customer->public_id,
                'name' => $customer->name,
            ],
            'merchant' => $merchant === null ? null : [
                'public_id' => $merchant->public_id,
                'name' => $merchant->name,
            ],
        ];

        if ($includeAudit) {
            $payload['extra_request_ledger'] = $payment->customerExtraRequestTransactions
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'amount' => $row->amount,
                    'type' => $row->type_formatted,
                    'source' => $row->source_formatted,
                    'customer' => $row->customer === null ? null : [
                        'public_id' => $row->customer->public_id,
                        'name' => $row->customer->name,
                    ],
                    'created_at' => $row->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();

            $payload['merchant_credit_ledger'] = $payment->merchantOfferCreditTransactions
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'amount' => $row->amount,
                    'paid_amount' => $this->paymentTransactionService->formatAmount($row->paid_amount),
                    'type' => $row->type_formatted,
                    'source' => $row->source_formatted,
                    'merchant' => $row->merchant === null ? null : [
                        'public_id' => $row->merchant->public_id,
                        'name' => $row->merchant->name,
                    ],
                    'created_at' => $row->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return $payload;
    }
}
