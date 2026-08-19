<?php

namespace App\Services;

use App\Models\HeroSlide;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HeroSlideService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'title_ar',
        'title_en',
        'description_ar',
        'description_en',
        'cta_text_ar',
        'cta_text_en',
        'cta_url',
        'ordering',
        'is_active',
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getPaginatedHeroSlides(
        string $search = '',
        string $sortBy = 'ordering',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        return HeroSlide::query()
            ->with('attachment')
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('description_ar', 'like', "%{$search}%")
                    ->orWhere('description_en', 'like', "%{$search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function orderingQuery(): Builder
    {
        return HeroSlide::query();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getActiveSlidesForPublic(): Collection
    {
        return HeroSlide::query()
            ->with('attachment')
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (HeroSlide $slide) => [
                'title_ar' => $slide->title_ar,
                'title_en' => $slide->title_en,
                'description_ar' => $slide->description_ar,
                'description_en' => $slide->description_en,
                'cta_text_ar' => $slide->cta_text_ar,
                'cta_text_en' => $slide->cta_text_en,
                'cta_url' => $slide->cta_url,
                'image' => $slide->attachment?->asset_path,
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, UploadedFile $image): HeroSlide
    {
        return DB::transaction(function () use ($data, $image) {
            $orderingQuery = $this->orderingQuery();

            if (! array_key_exists('ordering', $data) || $data['ordering'] === null) {
                $data['ordering'] = nextOrdering(model: $orderingQuery);
            } else {
                $data['ordering'] = (int) $data['ordering'];
                shiftOrdering(model: $orderingQuery, from: $data['ordering'], direction: 'up');
            }

            $heroSlide = HeroSlide::create($data);
            $this->storeImage(heroSlide: $heroSlide, image: $image);

            $this->activityLogService->recordCreated(
                subject: $heroSlide,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($heroSlide),
            );

            return $heroSlide;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(HeroSlide $heroSlide, array $data, ?UploadedFile $image = null): HeroSlide
    {
        return DB::transaction(function () use ($heroSlide, $data, $image) {
            $originalValues = $heroSlide->only(self::ACTIVITY_FIELDS);
            $orderingQuery = $this->orderingQuery();
            $oldOrdering = $heroSlide->ordering;
            $newOrdering = (int) ($data['ordering'] ?? $oldOrdering);

            if ($newOrdering !== $oldOrdering) {
                if ($newOrdering < $oldOrdering) {
                    shiftOrdering(model: $orderingQuery, from: $newOrdering, direction: 'up', to: $oldOrdering - 1, excludeId: $heroSlide->id);
                } else {
                    shiftOrdering(model: $orderingQuery, from: $oldOrdering, direction: 'down', to: $newOrdering, excludeId: $heroSlide->id);
                }
            }

            $heroSlide->update($data);

            if ($image) {
                $this->deleteImage(heroSlide: $heroSlide);
                $this->storeImage(heroSlide: $heroSlide, image: $image);
            }

            $this->activityLogService->recordChanges(
                subject: $heroSlide,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($heroSlide),
            );

            return $heroSlide;
        });
    }

    public function delete(HeroSlide $heroSlide): void
    {
        DB::transaction(function () use ($heroSlide) {
            $this->activityLogService->recordDeleted(
                subject: $heroSlide,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($heroSlide),
            );

            $this->deleteImage(heroSlide: $heroSlide);
            $ordering = $heroSlide->ordering;
            $heroSlide->delete();

            shiftOrdering(model: $this->orderingQuery(), from: $ordering, direction: 'down');
        });
    }

    private function subjectLabel(HeroSlide $heroSlide): string
    {
        return $heroSlide->title_ar ?: $heroSlide->title_en ?: 'Hero Slide';
    }

    private function storeImage(HeroSlide $heroSlide, UploadedFile $image): void
    {
        $path = $image->store('hero-slides', 'public');
        $heroSlide->attachment()->create([
            'name' => $image->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    private function deleteImage(HeroSlide $heroSlide): void
    {
        $attachment = $heroSlide->attachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }
}
