<?php

namespace App\Http\Controllers;

use App\Http\Requests\PageRequest;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PageController extends Controller
{
    public function __construct(public PageService $pageService) {}

    public function index(Request $request)
    {
        $search = (string) $request->input('search', '');
        $status = $request->filled('status')
            ? filter_var($request->input('status'), FILTER_VALIDATE_BOOLEAN)
            : null;
        $sortBy = (string) $request->input('sort_column', 'menu_order');
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $pages = $this->pageService->getPaginatedPages(
            search: $search,
            status: $status,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );

        return Inertia::render('Pages/PagesPage', [
            'pages' => $pages,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function store(PageRequest $request)
    {
        $this->pageService->store($request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(PageRequest $request, Page $page)
    {
        $this->pageService->update($page, $request->validated());

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(Page $page)
    {
        $this->pageService->delete($page);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
