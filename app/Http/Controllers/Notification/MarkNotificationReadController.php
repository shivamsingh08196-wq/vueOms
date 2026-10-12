<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MarkNotificationReadController extends Controller
{
    public function __invoke(Request $request, string $id)
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $notification = $user->notifications()->where('id', $id)->first();
        if (! $notification) {
            abort(404, 'Notification not found.');
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }
}

