<?php
namespace App\Policies;
use App\Models\User;

class PurchasePolicy {
    public function viewAny(User $user) {
        return $user->hasPermissionTo('view');
    }
    public function view(User $user) {
        return $user->hasPermissionTo('view');
    }
    public function create(User $user) {
        return $user->hasPermissionTo('create');
    }
    public function update(User $user) {
        return $user->hasPermissionTo('update');
    }
    public function delete(User $user) {
        return $user->hasPermissionTo('delete');
    }
}