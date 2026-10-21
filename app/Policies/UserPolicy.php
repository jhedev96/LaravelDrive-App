<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
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
        return $user->hasPermission('user.view');
    }

    public function create(User $user): ?bool
    {
        return $user->hasPermission('user.create');
    }

    public function update(User $user): ?bool
    {
        return $user->hasPermission('user.update');
    }

    public function delete(User $user): ?bool
    {
        return $user->hasPermission('user.delete');
    }
}
