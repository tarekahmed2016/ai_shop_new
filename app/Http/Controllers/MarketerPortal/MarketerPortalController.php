<?php

namespace App\Http\Controllers\MarketerPortal;

use App\Http\Controllers\Controller;
use App\Services\MarketerCommissionService;
use App\Services\MarketerService;
use App\Services\PaymentTransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketerPortalController extends Controller
{
    public function __construct(
        public MarketerService $marketerService,
        public PaymentTransactionService $paymentTransactionService,
        public MarketerCommissionService $commissionService,
    ) {}

    public function home(Request $request): Response
    {
        $marketer = $request->user()?->marketer;
        abort_unless($marketer !== null && $marketer->isActive(), 403);

        return Inertia::render('MarketerPortal/HomePage', [
            'metrics' => $this->marketerService->dashboardMetrics($marketer),
            'paymentSummary' => $this->paymentTransactionService->marketerReferralSummary($marketer),
            'financeSummary' => $this->commissionService->financialSummary($marketer),
        ]);
    }

    public function referrals(Request $request): Response
    {
        $marketer = $request->user()?->marketer;
        abort_unless($marketer !== null && $marketer->isActive(), 403);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'registered_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('MarketerPortal/ReferralsPage', [
            'referrals' => $this->marketerService->getOwnPaginatedReferrals(
                marketer: $marketer,
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

    public function payments(Request $request): Response
    {
        $marketer = $request->user()?->marketer;
        abort_unless($marketer !== null && $marketer->isActive(), 403);

        $payments = $this->paymentTransactionService->paginatedMarketerReferralPayments($marketer);
        $payments->getCollection()->transform(fn ($payment) => $this->presentMarketerPayment($payment));

        return Inertia::render('MarketerPortal/PaymentsPage', [
            'payments' => $payments,
            'summary' => $this->paymentTransactionService->marketerReferralSummary($marketer),
        ]);
    }

    public function commissions(Request $request): Response
    {
        $marketer = $request->user()?->marketer;
        abort_unless($marketer !== null && $marketer->isActive(), 403);

        $commissions = $this->commissionService->paginatedCommissions($marketer);
        $commissions->getCollection()->transform(
            fn ($commission) => $this->commissionService->presentPortalCommission($commission)
        );

        return Inertia::render('MarketerPortal/CommissionsPage', [
            'commissions' => $commissions,
            'summary' => $this->commissionService->financialSummary($marketer),
        ]);
    }

    public function payouts(Request $request): Response
    {
        $marketer = $request->user()?->marketer;
        abort_unless($marketer !== null && $marketer->isActive(), 403);

        $payouts = $this->commissionService->paginatedPayouts($marketer);
        $payouts->getCollection()->transform(
            fn ($payout) => $this->commissionService->presentPortalPayout($payout)
        );

        return Inertia::render('MarketerPortal/PayoutsPage', [
            'payouts' => $payouts,
            'summary' => $this->commissionService->financialSummary($marketer),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMarketerPayment($payment): array
    {
        $type = $payment->type;

        return [
            'id' => $payment->id,
            'public_id' => $payment->public_id,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'amount' => $this->paymentTransactionService->formatAmount($payment->amount),
            'payer_name' => $payment->payer?->name,
            'type' => $payment->type_formatted,
            'capability' => $type?->capabilityLabel(),
            'capability_name' => $type?->name === 'MerchantOfferCredits' ? 'Merchant' : ($type?->name === 'CustomerExtraRequests' ? 'Customer' : 'Other'),
            'customer_name' => $payment->relatedCustomer?->name,
            'merchant_name' => $payment->relatedMerchant?->name,
        ];
    }
}
