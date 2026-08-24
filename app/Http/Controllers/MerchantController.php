<?php

namespace App\Http\Controllers;

use App\Enums\MerchantOfferCredits\AdminPermission;
use App\Enums\MerchantOfferCredits\TransactionSource;
use App\Enums\Merchants\Status;
use App\Http\Requests\MerchantRequest;
use App\Models\Merchant;
use App\Services\CategoryService;
use App\Services\MerchantOfferCreditService;
use App\Services\MerchantService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantController extends Controller
{
    public function __construct(
        public MerchantService $merchantService,
        public CategoryService $categoryService,
        public MerchantOfferCreditService $merchantOfferCreditService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Merchant::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $merchants = $this->merchantService->getPaginatedMerchants(
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );

        $user = $request->user();

        return Inertia::render('Merchants/MerchantsPage', [
            'merchants' => $merchants,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
            'statuses' => Status::toArray(),
            'availableCategories' => $this->categoryService->activeCategoriesForAssignment(),
            'offerCreditEnforcement' => $this->merchantOfferCreditService->isEnforcementEnabled(),
            'creditSources' => TransactionSource::manualChoicesToArray(),
            'creditPermissions' => [
                'view' => $user?->can(AdminPermission::View->value) === true,
                'add' => $user?->can(AdminPermission::Add->value) === true,
                'deduct' => $user?->can(AdminPermission::Deduct->value) === true,
                'manageSettings' => $user?->can(AdminPermission::ManageSettings->value) === true,
            ],
        ]);
    }

    public function store(MerchantRequest $request)
    {
        $this->merchantService->store(data: $request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(MerchantRequest $request, Merchant $merchant)
    {
        $this->merchantService->update(merchant: $merchant, data: $request->validated());

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
