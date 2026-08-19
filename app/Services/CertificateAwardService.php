<?php

namespace App\Services;

use App\Enums\CertificateAwardType;
use App\Models\CertificateAward;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CertificateAwardService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'type',
        'title_ar',
        'title_en',
        'issuer_ar',
        'issuer_en',
        'description_ar',
        'description_en',
        'issued_date',
        'external_url',
        'ordering',
        'is_active',
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getPaginatedCertificateAwards(
        string $search = '',
        string $typeFilter = 'all',
        string $sortBy = 'type',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = CertificateAward::query()
            ->with('attachment')
            ->when($typeFilter !== 'all', fn ($q) => $q->where('type', $typeFilter))
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('issuer_ar', 'like', "%{$search}%")
                    ->orWhere('issuer_en', 'like', "%{$search}%")
                    ->orWhere('description_ar', 'like', "%{$search}%")
                    ->orWhere('description_en', 'like', "%{$search}%");
            }));

        if ($sortBy === 'type') {
            $query->orderBy('type', $sortDir)->orderBy('ordering', 'asc');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function orderingQuery(CertificateAwardType $type): Builder
    {
        return CertificateAward::query()->where('type', $type->value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, UploadedFile $image): CertificateAward
    {
        return DB::transaction(function () use ($data, $image) {
            $type = CertificateAwardType::from($data['type']);
            $orderingQuery = $this->orderingQuery($type);

            if (! array_key_exists('ordering', $data) || $data['ordering'] === null) {
                $data['ordering'] = nextOrdering(model: $orderingQuery);
            } else {
                $data['ordering'] = (int) $data['ordering'];
                shiftOrdering(model: $orderingQuery, from: $data['ordering'], direction: 'up');
            }

            $certificateAward = CertificateAward::create($data);
            $this->storeImage(certificateAward: $certificateAward, image: $image);

            $this->activityLogService->recordCreated(
                subject: $certificateAward,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($certificateAward),
            );

            return $certificateAward;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CertificateAward $certificateAward, array $data, ?UploadedFile $image = null): CertificateAward
    {
        return DB::transaction(function () use ($certificateAward, $data, $image) {
            $originalValues = $certificateAward->only(self::ACTIVITY_FIELDS);
            $originalType = $certificateAward->type;
            $newType = CertificateAwardType::from($data['type']);

            if ($originalType !== $newType) {
                $oldOrderingQuery = $this->orderingQuery($originalType);
                shiftOrdering(model: $oldOrderingQuery, from: $certificateAward->ordering, direction: 'down');

                $newOrderingQuery = $this->orderingQuery($newType);
                $newOrdering = (int) ($data['ordering'] ?? nextOrdering(model: $newOrderingQuery));
                shiftOrdering(model: $newOrderingQuery, from: $newOrdering, direction: 'up');
                $data['ordering'] = $newOrdering;
            } else {
                $orderingQuery = $this->orderingQuery($newType);
                $oldOrdering = $certificateAward->ordering;
                $newOrdering = (int) ($data['ordering'] ?? $oldOrdering);

                if ($newOrdering !== $oldOrdering) {
                    if ($newOrdering < $oldOrdering) {
                        shiftOrdering(model: $orderingQuery, from: $newOrdering, direction: 'up', to: $oldOrdering - 1, excludeId: $certificateAward->id);
                    } else {
                        shiftOrdering(model: $orderingQuery, from: $oldOrdering, direction: 'down', to: $newOrdering, excludeId: $certificateAward->id);
                    }
                }
            }

            $certificateAward->update($data);

            if ($image) {
                $this->deleteImage(certificateAward: $certificateAward);
                $this->storeImage(certificateAward: $certificateAward, image: $image);
            }

            $this->activityLogService->recordChanges(
                subject: $certificateAward,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($certificateAward),
            );

            return $certificateAward;
        });
    }

    public function delete(CertificateAward $certificateAward): void
    {
        DB::transaction(function () use ($certificateAward) {
            $this->activityLogService->recordDeleted(
                subject: $certificateAward,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($certificateAward),
            );

            $this->deleteImage(certificateAward: $certificateAward);
            $ordering = $certificateAward->ordering;
            $type = $certificateAward->type;
            $certificateAward->delete();

            shiftOrdering(model: $this->orderingQuery($type), from: $ordering, direction: 'down');
        });
    }

    private function subjectLabel(CertificateAward $certificateAward): string
    {
        $title = $certificateAward->title_ar ?: $certificateAward->title_en ?: 'Record';

        return $certificateAward->type->labelEn().': '.$title;
    }

    private function storeImage(CertificateAward $certificateAward, UploadedFile $image): void
    {
        $path = $image->store('certificates-awards', 'public');
        $certificateAward->attachment()->create([
            'name' => $image->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    private function deleteImage(CertificateAward $certificateAward): void
    {
        $attachment = $certificateAward->attachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }
}
