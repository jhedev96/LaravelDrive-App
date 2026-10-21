<?php

namespace App\Policies;

use App\Role;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasPermission('administrator')) {
            return true;
        }

        return null;
    }

    public function view(User $user): ?bool
    {
        return $user->hasPermission('role.view');
    }

    public function create(User $user): ?bool
    {
        return $user->hasPermission('role.create');
    }

    public function update(User $user): ?bool
    {
        return $user->hasPermission('role.update');
    }

    public function delete(User $user): ?bool
    {
        return $user->hasPermission('role.delete');
    }
}
