<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DeleteUserController extends Controller
{
    public function __invoke(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account.',
                'errors' => ['user' => ['You cannot delete your own account.']],
            ], 422);
        }

        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(null, 204);
    }
}
