<?php

namespace App\Http\Controllers;

use App\Support\AdminAccess;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function authorizeAdmin(string $permission): void
    {
        abort_unless(AdminAccess::allows(request()->user(), $permission), 403);
    }
}
