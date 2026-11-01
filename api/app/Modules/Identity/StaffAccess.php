<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Models\User;

final class StaffAccess
{
    /**
     * True only when a staff member is signed in through the staff flow
     * (token carries the `staff` ability) and holds one of $roles.
     */
    public static function check(User $user, Role ...$roles): bool
    {
        if (! $user->is_staff || ! $user->tokenCan('staff')) {
            return false;
        }

        $names = $roles === [] ? Role::staffRoles() : array_map(fn (Role $r) => $r->value, $roles);

        return $user->hasAnyRole($names);
    }
}
