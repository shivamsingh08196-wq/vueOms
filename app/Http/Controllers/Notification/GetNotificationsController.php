<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GetNotificationsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $query = $user->notifications();

        if ($request->boolean('unread') || $request->input('unread') === '1') {
            $query = $user->unreadNotifications();
        }

        $notifications = $query->latest()->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
                'data' => $n->data,
            ];
        });

        return response()->json([
            'data' => $notifications,
        ]);
    }
}

