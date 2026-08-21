<?php

namespace App\Services;

use App\Enums\Categories\Status;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'name_ar',
        'name_en',
        'slug',
        'parent_id',
        'status',
        'sort_order',
    ];

    public function __construct(
        public ActivityLogService $activityLogService,
    ) {}

    public function getPaginatedCategories(
        string $search = '',
        string $sortBy = 'sort_order',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $allowedSorts = ['id', 'name_ar', 'name_en', 'status', 'sort_order', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'sort_order';

        return Category::query()
            ->with('parent:id,public_id,name_ar,name_en')
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Category>
     */
    public function parentOptions(?Category $except = null): Collection
    {
        $query = Category::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->select(['id', 'public_id', 'name_ar', 'name_en', 'parent_id']);

        if ($except === null) {
            return $query->get();
        }

        $excludedIds = $this->descendantIds($except);
        $excludedIds[] = $except->id;

        return $query->whereNotIn('id', $excludedIds)->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Category
    {
        $data = $this->normalizeParent($data);

        $category = new Category;
        $category->public_id = (string) Str::ulid();
        $category->fill($data);
        $category->save();

        $this->activityLogService->recordCreated(
            subject: $category,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $category->name_en,
        );

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $data = $this->normalizeParent($data, $category);

        if ($this->wouldCreateCycle($category, $data['parent_id'] ?? null)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be nested under itself or one of its descendants.',
            ]);
        }

        $originalValues = $category->only(self::ACTIVITY_FIELDS);

        $category->update($data);

        $this->activityLogService->recordChanges(
            subject: $category,
            originalValues: $originalValues,
            allowedFields: self::ACTIVITY_FIELDS,
            subjectLabel: $category->name_en,
        );

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeParent(array $data, ?Category $category = null): array
    {
        $parentPublicId = $data['parent_id'] ?? null;

        if ($parentPublicId === null || $parentPublicId === '') {
            $data['parent_id'] = null;

            return $data;
        }

        $parent = Category::query()->where('public_id', $parentPublicId)->first();

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent category is invalid.',
            ]);
        }

        if ($category !== null && $parent->id === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $data['parent_id'] = $parent->id;

        return $data;
    }

    private function wouldCreateCycle(Category $category, ?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ($parentId === $category->id) {
            return true;
        }

        $current = Category::query()->find($parentId);
        $seen = [];

        while ($current !== null) {
            if ($current->id === $category->id) {
                return true;
            }

            if (isset($seen[$current->id])) {
                return true;
            }

            $seen[$current->id] = true;
            $current = $current->parent;
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function descendantIds(Category $category): array
    {
        $ids = [];
        $frontier = Category::query()->where('parent_id', $category->id)->pluck('id')->all();

        while ($frontier !== []) {
            foreach ($frontier as $id) {
                $ids[] = $id;
            }

            $frontier = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();
        }

        return $ids;
    }

    public function activeCategoriesForAssignment(): Collection
    {
        return Category::query()
            ->where('status', Status::Active)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'public_id', 'name_ar', 'name_en', 'parent_id']);
    }
}
