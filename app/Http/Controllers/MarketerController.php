<?php

namespace App\Http\Controllers;

use App\Enums\Marketers\Status;
use App\Enums\Payments\Method;
use App\Enums\Payments\Type as PaymentType;
use App\Exceptions\InvalidMarketerTransitionException;
use App\Http\Requests\MarketerCommissionOverrideRequest;
use App\Http\Requests\MarketerPayoutStoreRequest;
use App\Http\Requests\MarketerStoreRequest;
use App\Models\Marketer;
use App\Services\MarketerCommissionService;
use App\Services\MarketerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketerController extends Controller
{
    public function __construct(
        public MarketerService $marketerService,
        public MarketerCommissionService $commissionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Marketer::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $statusValue = $request->input('status');
        $status = is_numeric($statusValue) ? Status::tryFrom((int) $statusValue) : null;

        return Inertia::render('Marketers/MarketersPage', [
            'marketers' => $this->marketerService->getPaginatedMarketers(
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
                status: $status,
            ),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
                'status' => $status?->value,
            ],
            'statuses' => Status::toArray(),
            'pendingCount' => $this->marketerService->pendingCount(),
        ]);
    }

    public function store(MarketerStoreRequest $request): RedirectResponse
    {
        $this->marketerService->createByAdmin($request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function approve(Marketer $marketer): RedirectResponse
    {
        $this->authorize('approve', $marketer);

        return $this->runTransition(fn () => $this->marketerService->approve($marketer), 'تم قبول الطلب');
    }

    public function reject(Marketer $marketer): RedirectResponse
    {
        $this->authorize('reject', $marketer);

        return $this->runTransition(fn () => $this->marketerService->reject($marketer), 'تم رفض الطلب');
    }

    public function deactivate(Marketer $marketer): RedirectResponse
    {
        $this->authorize('deactivate', $marketer);

        return $this->runTransition(fn () => $this->marketerService->deactivate($marketer), 'تم إيقاف المسوق');
    }

    public function reactivate(Marketer $marketer): RedirectResponse
    {
        $this->authorize('reactivate', $marketer);

        return $this->runTransition(fn () => $this->marketerService->reactivate($marketer), 'تم إعادة تفعيل المسوق');
    }

    public function show(Marketer $marketer): Response
    {
        $this->authorize('view', $marketer);

        $marketer->load(['user:id,name,email']);
        $rates = $this->commissionService->globalRates();

        return Inertia::render('Marketers/MarketerShowPage', [
            'marketer' => [
                'public_id' => $marketer->public_id,
                'name' => $marketer->user?->name,
                'email' => $marketer->user?->email,
                'referral_code' => $marketer->referral_code,
                'status' => $marketer->status_formatted,
                'customer_commission_rate' => $marketer->customer_commission_rate,
                'merchant_commission_rate' => $marketer->merchant_commission_rate,
            ],
            'summary' => $this->commissionService->financialSummary($marketer),
            'globalRates' => $rates,
            'effectiveRates' => [
                'customer_extra_requests' => $this->commissionService->effectiveRate(
                    $marketer,
                    PaymentType::CustomerExtraRequests,
                ),
                'merchant_offer_credits' => $this->commissionService->effectiveRate(
                    $marketer,
                    PaymentType::MerchantOfferCredits,
                ),
            ],
            'methods' => Method::toArray(),
        ]);
    }

    public function commissions(Marketer $marketer): Response
    {
        $this->authorize('view', $marketer);
        $marketer->load(['user:id,name,email']);

        $commissions = $this->commissionService->paginatedCommissions($marketer);
        $commissions->getCollection()->transform(
            fn ($commission) => $this->commissionService->presentPortalCommission($commission)
        );

        return Inertia::render('Marketers/MarketerCommissionsPage', [
            'marketer' => [
                'public_id' => $marketer->public_id,
                'name' => $marketer->user?->name,
            ],
            'commissions' => $commissions,
            'summary' => $this->commissionService->financialSummary($marketer),
        ]);
    }

    public function payouts(Marketer $marketer): Response
    {
        $this->authorize('view', $marketer);
        $marketer->load(['user:id,name,email']);

        $payouts = $this->commissionService->paginatedPayouts($marketer);
        $payouts->load(['createdBy:id,name,email']);
        $payouts->getCollection()->transform(
            fn ($payout) => $this->commissionService->presentAdminPayout($payout)
        );

        return Inertia::render('Marketers/MarketerPayoutsPage', [
            'marketer' => [
                'public_id' => $marketer->public_id,
                'name' => $marketer->user?->name,
            ],
            'payouts' => $payouts,
            'summary' => $this->commissionService->financialSummary($marketer),
        ]);
    }

    public function storePayout(MarketerPayoutStoreRequest $request, Marketer $marketer): RedirectResponse
    {
        $this->commissionService->recordPayout(
            $marketer,
            $request->validated('amount'),
            $request->enum('payment_method', Method::class),
            $request->user(),
            $request->validated('reference'),
            $request->validated('notes'),
            $request->validated('paid_at'),
        );

        return redirect()->back()->with('success', 'تم تسجيل الدفعة');
    }

    public function updateRates(MarketerCommissionOverrideRequest $request, Marketer $marketer): RedirectResponse
    {
        $this->commissionService->updateMarketerOverrides(
            $marketer,
            $request->validated('customer_commission_rate'),
            $request->validated('merchant_commission_rate'),
        );

        return redirect()->back()->with('success', 'تم حفظ نسب العمولة');
    }

    private function runTransition(callable $callback, string $success): RedirectResponse
    {
        try {
            $callback();
        } catch (InvalidMarketerTransitionException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', $success);
    }
}
