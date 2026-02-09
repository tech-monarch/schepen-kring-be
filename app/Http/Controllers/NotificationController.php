<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Users can only see their own notifications
        $query = $user->notifications()
            ->with('notification')
            ->orderBy('user_notifications.created_at', 'desc');

        // Filter by read status
        if ($request->has('read')) {
            $query->where('read', filter_var($request->read, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $request->get('per_page', 20);
        $notifications = $query->paginate($perPage);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->unread_notifications_count
            ]
        ]);
    }

    public function markAsRead($id)
    {
        // User can only mark their own notifications as read
        $userNotification = UserNotification::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $userNotification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read'
        ]);
    }

    public function markAllAsRead()
    {
        // User can only mark their own notifications as read
        UserNotification::where('user_id', Auth::id())
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }

    public function getUnreadCount()
    {
        $count = Auth::user()->unread_notifications_count;

        return response()->json([
            'count' => $count
        ]);
    }

    public function delete($id)
    {
        // User can only delete their own notifications
        $userNotification = UserNotification::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $userNotification->delete();

        return response()->json([
            'message' => 'Notification deleted'
        ]);
    }
}