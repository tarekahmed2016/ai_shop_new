<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamMemberRequest;
use App\Models\TeamMember;
use App\Services\TeamMemberService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    public function __construct(public TeamMemberService $teamMemberService) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin('team-members.view');

        $search = (string) $request->input('search', '');
        $sortBy = in_array($request->input('sort_column'), ['id', 'name_ar', 'name_en', 'position_ar', 'position_en', 'email', 'ordering', 'created_at']) ? $request->input('sort_column') : 'ordering';
        $sortDir = $request->input('sort_direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $teamMembers = $this->teamMemberService->getPaginatedTeamMembers(search: $search, sortBy: $sortBy, sortDir: $sortDir);

        return Inertia::render('TeamMembers/TeamMembersPage', [
            'teamMembers' => $teamMembers,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
        ]);
    }

    public function getNextOrdering()
    {
        $this->authorizeAdmin('team-members.view');

        return response()->json([
            'ordering' => nextOrdering(model: $this->teamMemberService->orderingQuery()),
        ]);
    }

    public function store(TeamMemberRequest $request)
    {
        $this->teamMemberService->store(
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(TeamMemberRequest $request, TeamMember $teamMember)
    {
        $this->teamMemberService->update(
            teamMember: $teamMember,
            data: $request->safe()->except('image'),
            image: $request->file('image'),
        );

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(TeamMember $teamMember)
    {
        $this->authorizeAdmin('team-members.delete');

        $this->teamMemberService->delete(teamMember: $teamMember);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
