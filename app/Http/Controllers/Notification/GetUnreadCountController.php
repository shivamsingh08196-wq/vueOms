<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GetUnreadCountController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $count = $user->unreadNotifications()->count();

        return response()->json([
            'data' => [
                'count' => $count,
            ],
        ]);
    }
}

