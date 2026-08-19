<?php

namespace App\Services;

use App\Enums\HomepagePromoLayout;
use App\Enums\HomepagePromoType;
use App\Models\HomepagePromoBlock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HomepagePromoBlockService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'type',
        'title_ar',
        'title_en',
        'description_ar',
        'description_en',
        'cta_text_ar',
        'cta_text_en',
        'cta_url',
        'layout_variant',
        'ordering',
        'is_active',
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getPaginatedPromoBlocks(
        string $search = '',
        string $typeFilter = 'all',
        string $sortBy = 'ordering',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        return HomepagePromoBlock::query()
            ->with(['attachment', 'badgeAttachment'])
            ->when($typeFilter !== 'all' && in_array($typeFilter, HomepagePromoType::values(), true), fn ($q) => $q->where('type', $typeFilter))
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

    public function orderingQuery(?HomepagePromoType $type = null): Builder
    {
        return HomepagePromoBlock::query()
            ->when($type, fn ($query) => $query->where('type', $type));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveBusinessCtaForPublic(): ?array
    {
        $block = HomepagePromoBlock::query()
            ->where('type', HomepagePromoType::BusinessCta)
            ->where('is_active', true)
            ->orderBy('ordering')
            ->first();

        return $block ? $this->mapBlockForPublic($block) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveFeatureBandForPublic(): ?array
    {
        $block = HomepagePromoBlock::query()
            ->with(['attachment', 'badgeAttachment'])
            ->where('type', HomepagePromoType::FeatureBand)
            ->where('is_active', true)
            ->orderBy('ordering')
            ->first();

        return $block ? $this->mapBlockForPublic($block) : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getActivePromoStripsForPublic(): Collection
    {
        return HomepagePromoBlock::query()
            ->with(['attachment', 'badgeAttachment'])
            ->where('type', HomepagePromoType::PromoStrip)
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (HomepagePromoBlock $block) => $this->mapBlockForPublic($block))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(
        array $data,
        ?UploadedFile $image = null,
        ?UploadedFile $badgeImage = null,
    ): HomepagePromoBlock {
        return DB::transaction(function () use ($data, $image, $badgeImage) {
            $type = HomepagePromoType::from((string) $data['type']);
            $orderingQuery = $this->orderingQuery(type: $type);

            if (! array_key_exists('ordering', $data) || $data['ordering'] === null) {
                $data['ordering'] = nextOrdering(model: $orderingQuery);
            } else {
                $data['ordering'] = (int) $data['ordering'];
                shiftOrdering(model: $orderingQuery, from: $data['ordering'], direction: 'up');
            }

            $block = HomepagePromoBlock::create($data);

            if ($image) {
                $this->storeMainImage(block: $block, image: $image);
            }

            if ($badgeImage) {
                $this->storeBadgeImage(block: $block, image: $badgeImage);
            }

            $this->activityLogService->recordCreated(
                subject: $block,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($block),
            );

            return $block;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        HomepagePromoBlock $block,
        array $data,
        ?UploadedFile $image = null,
        ?UploadedFile $badgeImage = null,
        bool $removeBadge = false,
    ): HomepagePromoBlock {
        return DB::transaction(function () use ($block, $data, $image, $badgeImage, $removeBadge) {
            $originalValues = $block->only(self::ACTIVITY_FIELDS);
            $type = HomepagePromoType::from((string) ($data['type'] ?? $block->type->value));
            $orderingQuery = $this->orderingQuery(type: $type);
            $oldOrdering = $block->ordering;
            $newOrdering = (int) ($data['ordering'] ?? $oldOrdering);

            if ($newOrdering !== $oldOrdering) {
                if ($newOrdering < $oldOrdering) {
                    shiftOrdering(model: $orderingQuery, from: $newOrdering, direction: 'up', to: $oldOrdering - 1, excludeId: $block->id);
                } else {
                    shiftOrdering(model: $orderingQuery, from: $oldOrdering, direction: 'down', to: $newOrdering, excludeId: $block->id);
                }
            }

            $block->update($data);

            if ($image) {
                $this->deleteMainImage(block: $block);
                $this->storeMainImage(block: $block, image: $image);
            }

            if ($removeBadge) {
                $this->deleteBadgeImage(block: $block);
            }

            if ($badgeImage) {
                $this->deleteBadgeImage(block: $block);
                $this->storeBadgeImage(block: $block, image: $badgeImage);
            }

            $this->activityLogService->recordChanges(
                subject: $block,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($block),
            );

            return $block;
        });
    }

    public function delete(HomepagePromoBlock $block): void
    {
        DB::transaction(function () use ($block) {
            $this->activityLogService->recordDeleted(
                subject: $block,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($block),
            );

            $this->deleteMainImage(block: $block);
            $this->deleteBadgeImage(block: $block);
            $ordering = $block->ordering;
            $type = $block->type instanceof HomepagePromoType ? $block->type : HomepagePromoType::from((string) $block->type);
            $block->delete();

            shiftOrdering(model: $this->orderingQuery(type: $type), from: $ordering, direction: 'down');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBlockForPublic(HomepagePromoBlock $block): array
    {
        return [
            'type' => $block->type instanceof HomepagePromoType ? $block->type->value : (string) $block->type,
            'title_ar' => $block->title_ar,
            'title_en' => $block->title_en,
            'description_ar' => $block->description_ar,
            'description_en' => $block->description_en,
            'cta_text_ar' => $block->cta_text_ar,
            'cta_text_en' => $block->cta_text_en,
            'cta_url' => $block->cta_url,
            'layout_variant' => $block->layout_variant instanceof HomepagePromoLayout
                ? $block->layout_variant->value
                : (string) ($block->layout_variant ?? 'content_left'),
            'image' => $block->attachment?->asset_path,
            'badge_image' => $block->badgeAttachment?->asset_path,
        ];
    }

    private function subjectLabel(HomepagePromoBlock $block): string
    {
        return $block->title_ar ?: $block->title_en ?: 'Homepage Promo';
    }

    private function storeMainImage(HomepagePromoBlock $block, UploadedFile $image): void
    {
        $path = $image->store('homepage-promos', 'public');
        $block->attachment()->create([
            'name' => $image->getClientOriginalName(),
            'path' => $path,
            'collection' => 'default',
        ]);
    }

    private function storeBadgeImage(HomepagePromoBlock $block, UploadedFile $image): void
    {
        $path = $image->store('homepage-promos/badges', 'public');
        $block->badgeAttachment()->create([
            'name' => $image->getClientOriginalName(),
            'path' => $path,
            'collection' => 'badge',
        ]);
    }

    private function deleteMainImage(HomepagePromoBlock $block): void
    {
        $attachment = $block->attachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }

    private function deleteBadgeImage(HomepagePromoBlock $block): void
    {
        $attachment = $block->badgeAttachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }
}
