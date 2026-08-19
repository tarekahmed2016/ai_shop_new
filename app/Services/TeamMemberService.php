<?php

namespace App\Services;

use App\Models\TeamMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TeamMemberService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'name_ar',
        'name_en',
        'position_ar',
        'position_en',
        'bio_ar',
        'bio_en',
        'email',
        'phone',
        'linkedin_url',
        'ordering',
        'is_active',
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getPaginatedTeamMembers(
        string $search = '',
        string $sortBy = 'ordering',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        return TeamMember::query()
            ->with('attachment')
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('position_ar', 'like', "%{$search}%")
                    ->orWhere('position_en', 'like', "%{$search}%")
                    ->orWhere('bio_ar', 'like', "%{$search}%")
                    ->orWhere('bio_en', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function orderingQuery(): Builder
    {
        return TeamMember::query();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, UploadedFile $image): TeamMember
    {
        return DB::transaction(function () use ($data, $image) {
            $orderingQuery = $this->orderingQuery();

            if (! array_key_exists('ordering', $data) || $data['ordering'] === null) {
                $data['ordering'] = nextOrdering(model: $orderingQuery);
            } else {
                $data['ordering'] = (int) $data['ordering'];
                shiftOrdering(model: $orderingQuery, from: $data['ordering'], direction: 'up');
            }

            $teamMember = TeamMember::create($data);
            $this->storeImage(teamMember: $teamMember, image: $image);

            $this->activityLogService->recordCreated(
                subject: $teamMember,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($teamMember),
            );

            return $teamMember;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TeamMember $teamMember, array $data, ?UploadedFile $image = null): TeamMember
    {
        return DB::transaction(function () use ($teamMember, $data, $image) {
            $originalValues = $teamMember->only(self::ACTIVITY_FIELDS);
            $orderingQuery = $this->orderingQuery();
            $oldOrdering = $teamMember->ordering;
            $newOrdering = (int) ($data['ordering'] ?? $oldOrdering);

            if ($newOrdering !== $oldOrdering) {
                if ($newOrdering < $oldOrdering) {
                    shiftOrdering(model: $orderingQuery, from: $newOrdering, direction: 'up', to: $oldOrdering - 1, excludeId: $teamMember->id);
                } else {
                    shiftOrdering(model: $orderingQuery, from: $oldOrdering, direction: 'down', to: $newOrdering, excludeId: $teamMember->id);
                }
            }

            $teamMember->update($data);

            if ($image) {
                $this->deleteImage(teamMember: $teamMember);
                $this->storeImage(teamMember: $teamMember, image: $image);
            }

            $this->activityLogService->recordChanges(
                subject: $teamMember,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($teamMember),
            );

            return $teamMember;
        });
    }

    public function delete(TeamMember $teamMember): void
    {
        DB::transaction(function () use ($teamMember) {
            $this->activityLogService->recordDeleted(
                subject: $teamMember,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($teamMember),
            );

            $this->deleteImage(teamMember: $teamMember);
            $ordering = $teamMember->ordering;
            $teamMember->delete();

            shiftOrdering(model: $this->orderingQuery(), from: $ordering, direction: 'down');
        });
    }

    private function subjectLabel(TeamMember $teamMember): string
    {
        return $teamMember->name_ar ?: $teamMember->name_en ?: 'Team Member';
    }

    private function storeImage(TeamMember $teamMember, UploadedFile $image): void
    {
        $path = $image->store('team-members', 'public');
        $teamMember->attachment()->create([
            'name' => $image->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    private function deleteImage(TeamMember $teamMember): void
    {
        $attachment = $teamMember->attachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }
}
