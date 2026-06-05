<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RetryNotificationJob;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get notification history for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = NotificationLog::where('user_id', $user->id);

        if ($request->has('type')) {
            $query->where('notification_type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Get a specific notification.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $notification = NotificationLog::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $notification,
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $user = Auth::user();

        $notification = NotificationLog::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        NotificationLog::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();

        $count = NotificationLog::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $count],
        ]);
    }

    /**
     * Get notification statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $stats = [
            'total_notifications' => NotificationLog::count(),
            'sent' => NotificationLog::where('status', 'sent')->count(),
            'scheduled' => NotificationLog::where('status', 'scheduled')->count(),
            'failed' => NotificationLog::where('status', 'failed')->count(),
            'pending' => NotificationLog::where('status', 'pending')->count(),
            'by_channel' => [
                'sms' => NotificationLog::where('channel', 'sms')->count(),
                'email' => NotificationLog::where('channel', 'email')->count(),
                'push' => NotificationLog::where('channel', 'push')->count(),
            ],
            'today_sent' => NotificationLog::where('status', 'sent')
                ->whereDate('sent_at', today())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get failed notifications for retry (admin only).
     */
    public function failed(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $failed = NotificationLog::where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $failed,
        ]);
    }

    /**
     * Retry a failed notification (admin only).
     */
    public function retry(int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $notification = NotificationLog::findOrFail($id);

        if ($notification->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Only failed notifications can be retried.',
            ], 422);
        }

        RetryNotificationJob::dispatch($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification retry queued.',
        ]);
    }
}
