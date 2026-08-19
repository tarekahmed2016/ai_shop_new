<?php

namespace App\Services;

use App\Enums\ClientPartnerType;
use App\Models\ClientPartner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientPartnerService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'type',
        'name_ar',
        'name_en',
        'website',
        'ordering',
        'is_active',
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getPaginatedClientPartners(
        string $search = '',
        string $typeFilter = 'all',
        string $sortBy = 'type',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = ClientPartner::query()
            ->with('attachment')
            ->when($typeFilter !== 'all', fn ($q) => $q->where('type', $typeFilter))
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('website', 'like', "%{$search}%");
            }));

        if ($sortBy === 'type') {
            $query->orderBy('type', $sortDir)->orderBy('ordering', 'asc');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function orderingQuery(ClientPartnerType $type): Builder
    {
        return ClientPartner::query()->where('type', $type->value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, UploadedFile $image): ClientPartner
    {
        return DB::transaction(function () use ($data, $image) {
            $type = ClientPartnerType::from($data['type']);
            $orderingQuery = $this->orderingQuery($type);

            if (! array_key_exists('ordering', $data) || $data['ordering'] === null) {
                $data['ordering'] = nextOrdering(model: $orderingQuery);
            } else {
                $data['ordering'] = (int) $data['ordering'];
                shiftOrdering(model: $orderingQuery, from: $data['ordering'], direction: 'up');
            }

            $clientPartner = ClientPartner::create($data);
            $this->storeLogo(clientPartner: $clientPartner, image: $image);

            $this->activityLogService->recordCreated(
                subject: $clientPartner,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($clientPartner),
            );

            return $clientPartner;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClientPartner $clientPartner, array $data, ?UploadedFile $image = null): ClientPartner
    {
        return DB::transaction(function () use ($clientPartner, $data, $image) {
            $originalValues = $clientPartner->only(self::ACTIVITY_FIELDS);
            $originalType = $clientPartner->type;
            $newType = ClientPartnerType::from($data['type']);

            if ($originalType !== $newType) {
                $oldOrderingQuery = $this->orderingQuery($originalType);
                shiftOrdering(model: $oldOrderingQuery, from: $clientPartner->ordering, direction: 'down');

                $newOrderingQuery = $this->orderingQuery($newType);
                $newOrdering = (int) ($data['ordering'] ?? nextOrdering(model: $newOrderingQuery));
                shiftOrdering(model: $newOrderingQuery, from: $newOrdering, direction: 'up');
                $data['ordering'] = $newOrdering;
            } else {
                $orderingQuery = $this->orderingQuery($newType);
                $oldOrdering = $clientPartner->ordering;
                $newOrdering = (int) ($data['ordering'] ?? $oldOrdering);

                if ($newOrdering !== $oldOrdering) {
                    if ($newOrdering < $oldOrdering) {
                        shiftOrdering(model: $orderingQuery, from: $newOrdering, direction: 'up', to: $oldOrdering - 1, excludeId: $clientPartner->id);
                    } else {
                        shiftOrdering(model: $orderingQuery, from: $oldOrdering, direction: 'down', to: $newOrdering, excludeId: $clientPartner->id);
                    }
                }
            }

            $clientPartner->update($data);

            if ($image) {
                $this->deleteLogo(clientPartner: $clientPartner);
                $this->storeLogo(clientPartner: $clientPartner, image: $image);
            }

            $this->activityLogService->recordChanges(
                subject: $clientPartner,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($clientPartner),
            );

            return $clientPartner;
        });
    }

    public function delete(ClientPartner $clientPartner): void
    {
        DB::transaction(function () use ($clientPartner) {
            $this->activityLogService->recordDeleted(
                subject: $clientPartner,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($clientPartner),
            );

            $this->deleteLogo(clientPartner: $clientPartner);
            $ordering = $clientPartner->ordering;
            $type = $clientPartner->type;
            $clientPartner->delete();

            shiftOrdering(model: $this->orderingQuery($type), from: $ordering, direction: 'down');
        });
    }

    private function subjectLabel(ClientPartner $clientPartner): string
    {
        $name = $clientPartner->name_ar ?: $clientPartner->name_en ?: 'Record';

        return $clientPartner->type->labelEn().': '.$name;
    }

    private function storeLogo(ClientPartner $clientPartner, UploadedFile $image): void
    {
        $path = $image->store('clients-partners', 'public');
        $clientPartner->attachment()->create([
            'name' => $image->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    private function deleteLogo(ClientPartner $clientPartner): void
    {
        $attachment = $clientPartner->attachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }
}
