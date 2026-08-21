<?php

namespace App\Http\Controllers;

use App\Enums\MerchantMemberships\Role;
use App\Enums\MerchantMemberships\Status;
use App\Enums\MerchantPermissions\PermissionKey;
use App\Http\Requests\MerchantTeamStoreRequest;
use App\Http\Requests\MerchantTeamUpdateRequest;
use App\Models\MerchantUser;
use App\Models\User;
use App\Services\MerchantPermissionService;
use App\Services\MerchantTeamService;
use App\Support\MerchantAuthorization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantTeamController extends Controller
{
    public function __construct(
        public MerchantTeamService $merchantTeamService,
        public MerchantAuthorization $merchantAuthorization,
        public MerchantPermissionService $merchantPermissionService,
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->merchantAuthorization->canViewTeam(), 403);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $merchant = $this->merchantTeamService->currentMerchant();
        $actor = $this->merchantAuthorization->currentMembership();

        return Inertia::render('Merchants/MerchantTeamPage', [
            'merchant' => [
                'public_id' => $merchant->public_id,
                'name' => $merchant->name,
            ],
            'memberships' => $this->merchantTeamService->getPaginatedMembers(
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
            ),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
            'statuses' => Status::toArray(),
            'roles' => $this->merchantTeamService->assignableRolesForUi(),
            'canManageTeam' => $this->merchantAuthorization->canCreateMembers(),
            'permissionCatalog' => $this->merchantPermissionService->catalogGroupedForUi(),
            'assignablePermissions' => [
                'merchant-manager' => $this->merchantPermissionService->assignablePermissionKeysForActor(
                    $actor,
                    Role::Manager
                ),
                'merchant-staff' => $this->merchantPermissionService->assignablePermissionKeysForActor(
                    $actor,
                    Role::Staff
                ),
            ],
            'canCustomizePermissions' => $actor->role === Role::Owner
                || $this->merchantAuthorization->can(PermissionKey::TeamManagePermissions->value),
            'rolePermissionDefaults' => [
                'merchant-manager' => array_map(
                    fn ($key) => $key->value,
                    PermissionKey::managerDefaults()
                ),
                'merchant-staff' => array_map(
                    fn ($key) => $key->value,
                    PermissionKey::staffDefaults()
                ),
            ],
        ]);
    }

    public function lookup(Request $request)
    {
        abort_unless($this->merchantAuthorization->canCreateMembers(), 403);

        $email = strtolower(trim((string) $request->query('email', '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'exists' => false,
                'user' => null,
            ]);
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first(['id', 'name', 'email', 'phone']);

        return response()->json([
            'exists' => $user !== null,
            'user' => $user,
        ]);
    }

    public function store(MerchantTeamStoreRequest $request)
    {
        $this->merchantTeamService->addMember($request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(MerchantTeamUpdateRequest $request, MerchantUser $membership)
    {
        $this->merchantTeamService->updateMember($membership, $request->validated());

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(Request $request, MerchantUser $membership)
    {
        $this->merchantAuthorization->assertMembershipInCurrentMerchant($membership);
        $this->merchantAuthorization->assertCanRemoveMembership($membership);

        $this->merchantTeamService->removeMember($membership, $request);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
