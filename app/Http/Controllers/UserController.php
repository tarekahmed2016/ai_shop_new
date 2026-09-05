<?php

namespace App\Http\Controllers;

use App\Enums\Users\Status;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(public UserService $userService) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin('users.view');

        $search = (string) $request->input('search', '');
        $sortBy = in_array($request->input('sort_column'), ['id', 'name', 'email', 'created_at']) ? $request->input('sort_column') : 'created_at';
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $users = $this->userService->getPaginatedUsers(search: $search, sortBy: $sortBy, sortDir: $sortDir);

        return Inertia::render('Users/UsersPage', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
            'statuses' => Status::toArray(),
            'roles' => Role::query()->select('id', 'name')->get(),
        ]);
    }

    public function store(UserRequest $request)
    {
        $this->userService->store(data: $request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(UserRequest $request, User $user)
    {
        $this->userService->update(user: $user, data: $request->validated());

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(User $user)
    {
        $this->authorizeAdmin('users.delete');

        $this->userService->delete(user: $user);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
