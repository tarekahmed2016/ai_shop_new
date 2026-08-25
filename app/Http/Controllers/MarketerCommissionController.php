<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarketerCommissionSettingsRequest;
use App\Models\Marketer;
use App\Services\MarketerCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketerCommissionController extends Controller
{
    public function __construct(
        public MarketerCommissionService $commissionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Marketer::class);

        $summaries = $this->commissionService->paginatedAdminSummaries();
        $summaries->getCollection()->transform(
            fn (Marketer $marketer) => $this->commissionService->presentAdminSummary($marketer)
        );

        return Inertia::render('Marketers/MarketerCommissionsIndexPage', [
            'marketers' => $summaries,
            'rates' => $this->commissionService->globalRates(),
        ]);
    }

    public function updateSettings(MarketerCommissionSettingsRequest $request): RedirectResponse
    {
        $this->commissionService->setGlobalRates(
            $request->validated('customer_commission_rate'),
            $request->validated('merchant_commission_rate'),
            $request->user(),
            $request->validated('notes'),
        );

        return redirect()->back()->with('success', 'تم حفظ نسب العمولة');
    }
}
