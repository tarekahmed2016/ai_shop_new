<?php

namespace App\Http\Controllers;

use App\Enums\RequestMatches\Status as MatchStatus;
use App\Models\RequestMatch;
use App\Services\MerchantOfferCreditService;
use App\Services\MerchantRequestMatchService;
use App\Support\MerchantContext;
use App\Support\UserCapabilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        public MerchantContext $merchantContext,
        public MerchantRequestMatchService $merchantRequestMatchService,
        public MerchantOfferCreditService $merchantOfferCreditService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $isAdmin = (bool) $user?->hasRole('admin');
        $capabilities = $user ? UserCapabilities::for($user) : null;
        $hasMerchantMemberships = (bool) ($capabilities['hasMerchantMemberships'] ?? false);

        if ($user && ! $isAdmin && ! ($capabilities['hasActiveMerchantMemberships'] ?? false) && ! ($capabilities['hasCustomerPortalAccess'] ?? $capabilities['hasActiveCustomer'] ?? false)) {
            if ($capabilities['hasActiveMarketer'] ?? false) {
                return redirect()->route('marketer.home');
            }

            return redirect()->route('account.get-started');
        }

        // Customer-only users (no merchant capability) use the customer portal.
        if ($user && ! $isAdmin && ! ($capabilities['hasActiveMerchantMemberships'] ?? false) && ($capabilities['hasCustomerPortalAccess'] ?? $capabilities['hasActiveCustomer'] ?? false)) {
            return redirect()->route('customer.home');
        }

        $merchantWorkspace = null;

        if ($this->merchantContext->isActive()) {
            $merchant = $this->merchantContext->merchant();
            $merchantId = (int) $this->merchantContext->merchantId();

            $usage = $this->merchantRequestMatchService->usageCounters($merchantId);

            $merchantWorkspace = [
                'name' => $merchant?->name,
                'public_id' => $merchant?->public_id,
                'role' => $this->merchantContext->role()?->value,
                'categories_count' => $merchant?->categoryAssignments()->count() ?? 0,
                'available_requests_count' => RequestMatch::query()
                    ->where('merchant_id', $merchantId)
                    ->visibleToMerchant()
                    ->count(),
                'viewed_requests_count' => RequestMatch::query()
                    ->where('merchant_id', $merchantId)
                    ->where('status', MatchStatus::Viewed)
                    ->count(),
                'requests_received' => $usage['requests_received'],
                'offers_submitted' => $usage['offers_submitted'],
                'offer_credits' => $this->merchantOfferCreditService->presentForMerchant($merchantId),
            ];
        }

        return Inertia::render('Dashboard/IndexPage', [
            'isAdmin' => $isAdmin,
            'hasMerchantMemberships' => $hasMerchantMemberships,
            'merchantWorkspace' => $merchantWorkspace,
        ]);
    }
}
