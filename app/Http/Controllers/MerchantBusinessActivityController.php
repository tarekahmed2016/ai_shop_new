<?php

namespace App\Http\Controllers;

use App\Http\Requests\MerchantBusinessActivityRequest;
use App\Http\Requests\MerchantBusinessActivityWhatsAppRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Services\CategoryService;
use App\Services\MerchantCategoryService;
use App\Support\MerchantAuthorization;
use App\Support\MerchantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantBusinessActivityController extends Controller
{
    public function __construct(
        public MerchantCategoryService $merchantCategoryService,
        public CategoryService $categoryService,
        public MerchantAuthorization $merchantAuthorization,
        public MerchantContext $merchantContext,
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->merchantAuthorization->canViewActivities(), 403);

        /** @var Merchant $merchant */
        $merchant = $this->merchantContext->merchant();

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('Merchants/MerchantBusinessActivitiesPage', [
            'merchant' => [
                'public_id' => $merchant->public_id,
                'name' => $merchant->name,
            ],
            'assignments' => $this->merchantCategoryService->getPaginatedAssignments(
                merchant: $merchant,
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
            ),
            'availableCategories' => $this->categoryService->activeCategoriesForAssignment(),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
            'canManageActivities' => $this->merchantAuthorization->canManageActivities(),
        ]);
    }

    public function store(MerchantBusinessActivityRequest $request)
    {
        /** @var Merchant $merchant */
        $merchant = $this->merchantContext->merchant();

        $this->merchantCategoryService->attach(
            merchant: $merchant,
            categoryPublicId: $request->validated('category_id'),
            whatsappPhone: $request->validated('whatsapp_phone'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(MerchantBusinessActivityWhatsAppRequest $request, MerchantCategory $merchantCategory)
    {
        /** @var Merchant $merchant */
        $merchant = $this->merchantContext->merchant();

        abort_unless($merchantCategory->merchant_id === $merchant->id, 404);

        $this->merchantCategoryService->updateWhatsappPhone(
            merchant: $merchant,
            assignment: $merchantCategory,
            whatsappPhone: $request->validated('whatsapp_phone'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(Request $request, MerchantCategory $merchantCategory)
    {
        abort_unless($this->merchantAuthorization->canManageActivities(), 403);

        /** @var Merchant $merchant */
        $merchant = $this->merchantContext->merchant();

        abort_unless($merchantCategory->merchant_id === $merchant->id, 404);

        $this->merchantCategoryService->detach(merchant: $merchant, assignment: $merchantCategory);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
