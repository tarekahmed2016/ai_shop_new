<?php

namespace App\Http\Controllers;

use App\Http\Requests\HeroSlideRequest;
use App\Models\HeroSlide;
use App\Services\HeroSlideService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HeroSlideController extends Controller
{
    public function __construct(public HeroSlideService $heroSlideService) {}

    public function index(Request $request)
    {
        $search = (string) $request->input('search', '');
        $sortBy = in_array($request->input('sort_column'), ['id', 'title_ar', 'title_en', 'ordering', 'created_at']) ? $request->input('sort_column') : 'ordering';
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $heroSlides = $this->heroSlideService->getPaginatedHeroSlides(search: $search, sortBy: $sortBy, sortDir: $sortDir);

        return Inertia::render('HeroSlides/HeroSlidesPage', [
            'heroSlides' => $heroSlides,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function getNextOrdering()
    {
        return response()->json([
            'ordering' => nextOrdering(model: $this->heroSlideService->orderingQuery()),
        ]);
    }

    public function store(HeroSlideRequest $request)
    {
        $this->heroSlideService->store(
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(HeroSlideRequest $request, HeroSlide $heroSlide)
    {
        $this->heroSlideService->update(
            heroSlide: $heroSlide,
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(HeroSlide $heroSlide)
    {
        $this->heroSlideService->delete(heroSlide: $heroSlide);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
