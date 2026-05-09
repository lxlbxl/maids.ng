<?php

namespace App\Http\Controllers\Api;

use App\Jobs\RetryNotificationJob;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class NotificationController extends ApiController
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

        return $this->paginated($notifications, 'Notifications retrieved successfully');
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

        return $this->success($notification, 'Notification retrieved successfully');
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

        return $this->success(null, 'Notification marked as read.');
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

        return $this->success(null, 'All notifications marked as read.');
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

        return $this->success(['unread_count' => $count], 'Unread count retrieved successfully');
    }

    /**
     * Get notification statistics (admin only).
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
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

        return $this->success($stats, 'Notification statistics retrieved successfully');
    }

    /**
     * Get failed notifications for retry (admin only).
     */
    public function failed(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
        }

        $failed = NotificationLog::where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->paginated($failed, 'Failed notifications retrieved successfully');
    }

    /**
     * Retry a failed notification (admin only).
     */
    public function retry(int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return $this->forbidden('Unauthorized. Admin access required.');
        }

        $notification = NotificationLog::findOrFail($id);

        if ($notification->status !== 'failed') {
            return $this->error('Only failed notifications can be retried.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        RetryNotificationJob::dispatch($notification);

        return $this->success(null, 'Notification retry queued.');
    }
}
