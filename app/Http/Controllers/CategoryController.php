<?php

namespace App\Http\Controllers;

use App\Enums\Categories\Status;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(public CategoryService $categoryService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Category::class);

        $search = (string) $request->input('search', '');
        $sortBy = (string) $request->input('sort_column', 'sort_order');
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        return Inertia::render('Categories/CategoriesPage', [
            'categories' => $this->categoryService->getPaginatedCategories(
                search: $search,
                sortBy: $sortBy,
                sortDir: $sortDir,
            ),
            'parentOptions' => $this->categoryService->parentOptions(),
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
            'statuses' => Status::toArray(),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        $this->categoryService->store(data: $request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $this->categoryService->update(category: $category, data: $request->validated());

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
