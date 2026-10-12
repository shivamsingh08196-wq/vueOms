<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage') || $user->can('orders.assign') || $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.manage') || $user->hasRole('admin') || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage') || $user->hasRole('admin');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.manage') || $user->hasRole('admin') || $user->id === $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return ($user->can('users.manage') || $user->hasRole('admin')) && $user->id !== $target->id;
    }
}
