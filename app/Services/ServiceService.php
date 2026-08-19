<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServiceService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'ordering',
        'is_active',
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getPaginatedServices(
        string $search = '',
        string $sortBy = 'ordering',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        return Service::query()
            ->with('attachment')
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('description_ar', 'like', "%{$search}%")
                    ->orWhere('description_en', 'like', "%{$search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function orderingQuery(): Builder
    {
        return Service::query();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, UploadedFile $image): Service
    {
        return DB::transaction(function () use ($data, $image) {
            $orderingQuery = $this->orderingQuery();

            if (! array_key_exists('ordering', $data) || $data['ordering'] === null) {
                $data['ordering'] = nextOrdering(model: $orderingQuery);
            } else {
                $data['ordering'] = (int) $data['ordering'];
                shiftOrdering(model: $orderingQuery, from: $data['ordering'], direction: 'up');
            }

            $service = Service::create($data);
            $this->storeImage(service: $service, image: $image);

            $this->activityLogService->recordCreated(
                subject: $service,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($service),
            );

            return $service;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Service $service, array $data, ?UploadedFile $image = null): Service
    {
        return DB::transaction(function () use ($service, $data, $image) {
            $originalValues = $service->only(self::ACTIVITY_FIELDS);
            $orderingQuery = $this->orderingQuery();
            $oldOrdering = $service->ordering;
            $newOrdering = (int) ($data['ordering'] ?? $oldOrdering);

            if ($newOrdering !== $oldOrdering) {
                if ($newOrdering < $oldOrdering) {
                    shiftOrdering(model: $orderingQuery, from: $newOrdering, direction: 'up', to: $oldOrdering - 1, excludeId: $service->id);
                } else {
                    shiftOrdering(model: $orderingQuery, from: $oldOrdering, direction: 'down', to: $newOrdering, excludeId: $service->id);
                }
            }

            $service->update($data);

            if ($image) {
                $this->deleteImage(service: $service);
                $this->storeImage(service: $service, image: $image);
            }

            $this->activityLogService->recordChanges(
                subject: $service,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($service),
            );

            return $service;
        });
    }

    public function delete(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $this->activityLogService->recordDeleted(
                subject: $service,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($service),
            );

            $this->deleteImage(service: $service);
            $ordering = $service->ordering;
            $service->delete();

            shiftOrdering(model: $this->orderingQuery(), from: $ordering, direction: 'down');
        });
    }

    private function subjectLabel(Service $service): string
    {
        return $service->name_ar ?: $service->name_en ?: 'Service';
    }

    private function storeImage(Service $service, UploadedFile $image): void
    {
        $path = $image->store('services', 'public');
        $service->attachment()->create([
            'name' => $image->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    private function deleteImage(Service $service): void
    {
        $attachment = $service->attachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }
}
