<?php

namespace App\Support;

use App\Models\User;

final class AdminAccess
{
    public static function allows(?User $user, string $permission): bool
    {
        return $user !== null
            && $user->hasRole('admin')
            && $user->can($permission);
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function allowsAny(?User $user, array $permissions): bool
    {
        if ($user === null || ! $user->hasRole('admin')) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function canUploadRichText(?User $user): bool
    {
        return self::allowsAny($user, AdminPermissionCatalog::richTextUploadPermissions());
    }
}
