<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MarkAllNotificationsReadController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }
}

