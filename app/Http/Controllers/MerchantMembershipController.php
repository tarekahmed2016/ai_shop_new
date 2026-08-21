<?php

namespace App\Http\Controllers;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status;
use App\Http\Requests\MerchantMembershipRequest;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantMembershipService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantMembershipController extends Controller
{
    public function __construct(public MerchantMembershipService $merchantMembershipService) {}

    public function index(Request $request, Merchant $merchant)
    {
        $this->authorize('viewAny', MerchantUser::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $memberships = $this->merchantMembershipService->getPaginatedMemberships(
            merchant: $merchant,
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );

        return Inertia::render('Merchants/MerchantMembershipsPage', [
            'merchant' => $merchant,
            'memberships' => $memberships,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
            'statuses' => Status::toArray(),
            'roles' => Role::toArray(),
        ]);
    }

    public function store(MerchantMembershipRequest $request, Merchant $merchant)
    {
        $this->merchantMembershipService->store(merchant: $merchant, data: $request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(MerchantMembershipRequest $request, Merchant $merchant, MerchantUser $membership)
    {
        abort_unless($membership->merchant_id === $merchant->id, 404);

        $this->merchantMembershipService->update(membership: $membership, data: $request->validated());

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(Request $request, Merchant $merchant, MerchantUser $membership)
    {
        $this->authorize('delete', $membership);

        abort_unless($membership->merchant_id === $merchant->id, 404);

        $this->merchantMembershipService->delete(membership: $membership, request: $request);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
