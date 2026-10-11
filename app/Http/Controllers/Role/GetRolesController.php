<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class GetRolesController extends Controller
{
    public function __invoke()
    {
        $this->authorizePermission('roles.manage');

        $roles = Role::with('permissions')->get();
        $allPermissions = Permission::all();

        return response()->json([
            'roles' => $roles,
            'all_permissions' => $allPermissions,
        ]);
    }

    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can($permission) && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized.');
        }
    }
}
