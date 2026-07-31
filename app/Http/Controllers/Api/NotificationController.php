<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return $user->notifications;
    }

    public function markAsRead($id)
    {
        $user = auth()->user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['message' => 'Notification marked as read'], 200);
    }

    public function markAsUnread($id)
    {
        $user = auth()->user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsUnread();
        return response()->json(['message' => 'Notification marked as unread'], 200);
    }

    public function markAllAsRead()
    {
        $user = auth()->user();
        $user->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All notifications marked as read'], 200);
    }
}
