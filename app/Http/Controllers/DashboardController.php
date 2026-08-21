<?php

namespace App\Http\Controllers;

use App\Enums\RequestMatches\Status as MatchStatus;
use App\Models\RequestMatch;
use App\Support\MerchantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        public MerchantContext $merchantContext,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $isAdmin = (bool) $user?->hasRole('admin');
        $hasMerchantMemberships = (bool) $user?->merchantMemberships()->exists();

        // Customer-only accounts use the dedicated portal, not the admin/merchant dashboard.
        if ($user && ! $isAdmin && ! $hasMerchantMemberships && $user->customer) {
            return redirect()->route('customer.home');
        }

        $merchantWorkspace = null;

        if ($this->merchantContext->isActive()) {
            $merchant = $this->merchantContext->merchant();
            $merchantId = (int) $this->merchantContext->merchantId();

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
            ];
        }

        return Inertia::render('Dashboard/IndexPage', [
            'isAdmin' => $isAdmin,
            'hasMerchantMemberships' => $hasMerchantMemberships,
            'merchantWorkspace' => $merchantWorkspace,
        ]);
    }
}
