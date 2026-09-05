<?php

namespace App\Http\Controllers;

use App\Enums\HomepagePromoType;
use App\Http\Requests\HomepagePromoBlockRequest;
use App\Models\HomepagePromoBlock;
use App\Services\HomepagePromoBlockService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomepagePromoBlockController extends Controller
{
    public function __construct(public HomepagePromoBlockService $homepagePromoBlockService) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin('homepage-promos.view');

        $search = (string) $request->input('search', '');
        $typeFilter = in_array($request->input('type'), array_merge(['all'], HomepagePromoType::values())) ? $request->input('type') : 'all';
        $sortBy = in_array($request->input('sort_column'), ['id', 'title_ar', 'title_en', 'type', 'ordering', 'created_at']) ? $request->input('sort_column') : 'ordering';
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $homepagePromoBlocks = $this->homepagePromoBlockService->getPaginatedPromoBlocks(
            search: $search,
            typeFilter: $typeFilter,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );

        return Inertia::render('HomepagePromos/HomepagePromosPage', [
            'homepagePromoBlocks' => $homepagePromoBlocks,
            'promoTypes' => collect(HomepagePromoType::cases())->map(fn (HomepagePromoType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'name' => $type->labelEn(),
            ])->values(),
            'filters' => [
                'search' => $search,
                'type' => $typeFilter,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function getNextOrdering(Request $request)
    {
        $this->authorizeAdmin('homepage-promos.view');

        $type = HomepagePromoType::tryFrom((string) $request->input('type', HomepagePromoType::FeatureBand->value))
            ?? HomepagePromoType::FeatureBand;

        return response()->json([
            'ordering' => nextOrdering(model: $this->homepagePromoBlockService->orderingQuery(type: $type)),
        ]);
    }

    public function store(HomepagePromoBlockRequest $request)
    {
        $this->homepagePromoBlockService->store(
            data: $request->safe()->except(['image', 'badge_image', 'remove_badge']),
            image: $request->file('image'),
            badgeImage: $request->file('badge_image'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(HomepagePromoBlockRequest $request, HomepagePromoBlock $homepagePromo)
    {
        $this->homepagePromoBlockService->update(
            block: $homepagePromo,
            data: $request->safe()->except(['image', 'badge_image', 'remove_badge']),
            image: $request->file('image'),
            badgeImage: $request->file('badge_image'),
            removeBadge: (bool) $request->input('remove_badge', false),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(HomepagePromoBlock $homepagePromo)
    {
        $this->authorizeAdmin('homepage-promos.delete');

        $this->homepagePromoBlockService->delete(block: $homepagePromo);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
