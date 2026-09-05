<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(public ProjectService $projectService) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin('projects.view');

        $search = (string) $request->input('search', '');
        $sortBy = in_array($request->input('sort_column'), ['id', 'name_ar', 'name_en', 'client_name_ar', 'client_name_en', 'ordering', 'project_date', 'created_at']) ? $request->input('sort_column') : 'ordering';
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $projects = $this->projectService->getPaginatedProjects(search: $search, sortBy: $sortBy, sortDir: $sortDir);

        return Inertia::render('Projects/ProjectsPage', [
            'projects' => $projects,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function getNextOrdering()
    {
        $this->authorizeAdmin('projects.view');

        return response()->json([
            'ordering' => nextOrdering(model: $this->projectService->orderingQuery()),
        ]);
    }

    public function store(ProjectRequest $request)
    {
        $this->projectService->store(
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(ProjectRequest $request, Project $project)
    {
        $this->projectService->update(
            project: $project,
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(Project $project)
    {
        $this->authorizeAdmin('projects.delete');

        $this->projectService->delete(project: $project);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
