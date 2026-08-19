<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PageService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'title_ar',
        'title_en',
        'menu_title_ar',
        'menu_title_en',
        'slug',
        'show_in_main_menu',
        'menu_order',
        'is_active',
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getPaginatedPages(
        string $search = '',
        ?bool $status = null,
        string $sortBy = 'menu_order',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSort = ['id', 'title_ar', 'title_en', 'slug', 'menu_order', 'is_active', 'created_at'];

        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'menu_order';
        }

        return Page::query()
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('menu_title_ar', 'like', "%{$search}%")
                    ->orWhere('menu_title_en', 'like', "%{$search}%");
            }))
            ->when($status !== null, fn ($q) => $q->where('is_active', $status))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, array{slug: string, menu_title_ar: string, menu_title_en: string, menu_order: int}>
     */
    public function getPublicMenuPages(): Collection
    {
        return Page::query()
            ->where('is_active', true)
            ->where('show_in_main_menu', true)
            ->orderBy('menu_order')
            ->get()
            ->map(fn (Page $page) => [
                'slug' => $page->slug,
                'menu_title_ar' => $page->menu_title_ar ?: $page->title_ar,
                'menu_title_en' => $page->menu_title_en ?: $page->title_en,
                'menu_order' => $page->menu_order,
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Page
    {
        $page = Page::create($data);

        $this->activityLogService->recordCreated(
            subject: $page,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $this->subjectLabel($page),
        );

        return $page;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Page $page, array $data): Page
    {
        $originalValues = $page->only(self::ACTIVITY_FIELDS);
        $page->update($data);

        $this->activityLogService->recordChanges(
            subject: $page,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $this->subjectLabel($page),
        );

        return $page;
    }

    public function delete(Page $page): void
    {
        $this->activityLogService->recordDeleted(
            subject: $page,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $this->subjectLabel($page),
        );

        $page->delete();
    }

    private function subjectLabel(Page $page): string
    {
        return $page->title_ar ?: $page->title_en ?: 'Page';
    }
}
