<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UpdateRolePermissionsController extends Controller
{
    public function __invoke(Request $request, Role $role)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('roles.manage') && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'required|string|exists:permissions,name',
        ]);

        $permissions = $validated['permissions'];

        // Lock-out protection: admin role must retain core permissions
        if ($role->name === 'admin') {
            $requiredCore = ['users.manage', 'roles.manage'];
            foreach ($requiredCore as $corePerm) {
                if (! in_array($corePerm, $permissions, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot remove core management permissions from the admin role.',
                        'errors' => ['permissions' => ['Cannot remove core management permissions from the admin role.']],
                    ], 422);
                }
            }
        }

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully',
            'role' => $role->load('permissions'),
        ]);
    }
}
