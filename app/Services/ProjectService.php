<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = [
        'name_ar',
        'name_en',
        'client_name_ar',
        'client_name_en',
        'description_ar',
        'description_en',
        'project_date',
        'project_url',
        'ordering',
        'is_active',
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getPaginatedProjects(
        string $search = '',
        string $sortBy = 'ordering',
        string $sortDir = 'asc',
        int $perPage = 15
    ): LengthAwarePaginator {
        return Project::query()
            ->with('attachment')
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('client_name_ar', 'like', "%{$search}%")
                    ->orWhere('client_name_en', 'like', "%{$search}%")
                    ->orWhere('description_ar', 'like', "%{$search}%")
                    ->orWhere('description_en', 'like', "%{$search}%");
            }))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function orderingQuery(): Builder
    {
        return Project::query();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, UploadedFile $image): Project
    {
        return DB::transaction(function () use ($data, $image) {
            $orderingQuery = $this->orderingQuery();

            if (! array_key_exists('ordering', $data) || $data['ordering'] === null) {
                $data['ordering'] = nextOrdering(model: $orderingQuery);
            } else {
                $data['ordering'] = (int) $data['ordering'];
                shiftOrdering(model: $orderingQuery, from: $data['ordering'], direction: 'up');
            }

            $project = Project::create($data);
            $this->storeImage(project: $project, image: $image);

            $this->activityLogService->recordCreated(
                subject: $project,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($project),
            );

            return $project;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data, ?UploadedFile $image = null): Project
    {
        return DB::transaction(function () use ($project, $data, $image) {
            $originalValues = $project->only(self::ACTIVITY_FIELDS);
            $orderingQuery = $this->orderingQuery();
            $oldOrdering = $project->ordering;
            $newOrdering = (int) ($data['ordering'] ?? $oldOrdering);

            if ($newOrdering !== $oldOrdering) {
                if ($newOrdering < $oldOrdering) {
                    shiftOrdering(model: $orderingQuery, from: $newOrdering, direction: 'up', to: $oldOrdering - 1, excludeId: $project->id);
                } else {
                    shiftOrdering(model: $orderingQuery, from: $oldOrdering, direction: 'down', to: $newOrdering, excludeId: $project->id);
                }
            }

            $project->update($data);

            if ($image) {
                $this->deleteImage(project: $project);
                $this->storeImage(project: $project, image: $image);
            }

            $this->activityLogService->recordChanges(
                subject: $project,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($project),
            );

            return $project;
        });
    }

    public function delete(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $this->activityLogService->recordDeleted(
                subject: $project,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($project),
            );

            $this->deleteImage(project: $project);
            $ordering = $project->ordering;
            $project->delete();

            shiftOrdering(model: $this->orderingQuery(), from: $ordering, direction: 'down');
        });
    }

    private function subjectLabel(Project $project): string
    {
        return $project->name_ar ?: $project->name_en ?: 'Project';
    }

    private function storeImage(Project $project, UploadedFile $image): void
    {
        $path = $image->store('projects', 'public');
        $project->attachment()->create([
            'name' => $image->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    private function deleteImage(Project $project): void
    {
        $attachment = $project->attachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }
}
