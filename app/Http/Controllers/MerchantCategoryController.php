<?php

namespace App\Http\Controllers;

use App\Http\Requests\MerchantCategoryRequest;
use App\Http\Requests\MerchantCategoryWhatsAppRequest;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Services\CategoryService;
use App\Services\MerchantCategoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantCategoryController extends Controller
{
    public function __construct(
        public MerchantCategoryService $merchantCategoryService,
        public CategoryService $categoryService,
    ) {}

    public function index(Request $request, Merchant $merchant)
    {
        $this->authorize('viewAny', MerchantCategory::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('Merchants/MerchantCategoriesPage', [
            'merchant' => $merchant,
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
        ]);
    }

    public function store(MerchantCategoryRequest $request, Merchant $merchant)
    {
        $this->merchantCategoryService->attach(
            merchant: $merchant,
            categoryPublicId: $request->validated('category_id'),
            whatsappPhone: $request->validated('whatsapp_phone'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(MerchantCategoryWhatsAppRequest $request, Merchant $merchant, MerchantCategory $merchantCategory)
    {
        $this->merchantCategoryService->updateWhatsappPhone(
            merchant: $merchant,
            assignment: $merchantCategory,
            whatsappPhone: $request->validated('whatsapp_phone'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(Request $request, Merchant $merchant, MerchantCategory $merchantCategory)
    {
        $this->authorize('delete', $merchantCategory);

        $this->merchantCategoryService->detach(merchant: $merchant, assignment: $merchantCategory);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
