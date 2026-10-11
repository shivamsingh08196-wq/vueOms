<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;

class GetUserController extends Controller
{
    public function __invoke(User $user)
    {
        $this->authorize('view', $user);

        return response()->json($user->load('roles'));
    }
}
