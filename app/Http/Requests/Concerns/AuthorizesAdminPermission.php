<?php

namespace App\Http\Requests\Concerns;

use App\Support\AdminAccess;

trait AuthorizesAdminPermission
{
    protected function authorizeAdmin(string $permission): bool
    {
        return AdminAccess::allows($this->user(), $permission);
    }

    protected function authorizeAdminMutation(string $createPermission, string $updatePermission, string $routeParameter): bool
    {
        $permission = $this->route($routeParameter) ? $updatePermission : $createPermission;

        return $this->authorizeAdmin($permission);
    }
}
