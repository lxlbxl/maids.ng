<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MaidProfile;
use App\Models\EmployerPreference;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Dispute;
use App\Models\User;
use App\Models\Assignment;
use App\Models\WalletTransaction;
use App\Models\Notification;
use App\Models\MaidAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CliAgentController extends Controller
{
    /**
     * Log all CLI agent actions for audit trail.
     */
    protected function logAction(string $action, array $data, ?int $userId = null): void
    {
        Log::channel('audit')->info('CLI Agent Action', [
            'action' => $action,
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the target user ID from header or request body.
     */
    protected function getTargetUserId(Request $request): ?int
    {
        return $request->header('X-User-ID')
            ?? $request->input('user_id')
            ?? null;
    }

    // =========================================================================
    // System Status & Health
    // =========================================================================

    public function status()
    {
        $this->logAction('status_check', []);

        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '2.0.0'),
        ]);
    }

    public function health()
    {
        $this->logAction('health_check', []);

        return response()->json([
            'healthy' => true,
            'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
            'cache' => config('cache.default'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // =========================================================================
    // Maid Management (Admin-level access)
    // =========================================================================

    public function getMaidProfile($maid_id)
    {
        $maid = MaidProfile::with('user')->where('user_id', $maid_id)->firstOrFail();

        $this->logAction('get_maid_profile', ['maid_id' => $maid_id]);

        return response()->json(['data' => $maid]);
    }

    public function updateMaidAvailability(Request $request, $maid_id)
    {
        $request->validate(['is_available' => 'required|boolean']);
        $maid = MaidProfile::where('user_id', $maid_id)->firstOrFail();
        $maid->availability_status = $request->is_available ? 'available' : 'unavailable';
        $maid->save();

        $this->logAction('update_maid_availability', [
            'maid_id' => $maid_id,
            'is_available' => $request->is_available,
        ]);

        return response()->json(['message' => 'Availability updated', 'data' => $maid]);
    }

    public function getMaidEarnings($maid_id)
    {
        $wallet = DB::table('maid_wallet')->where('user_id', $maid_id)->first();

        $this->logAction('get_maid_earnings', ['maid_id' => $maid_id]);

        return response()->json(['data' => ['wallet' => $wallet]]);
    }

    public function listMaids(Request $request)
    {
        $query = MaidProfile::with('user');

        if ($request->filled('location')) {
            $query->where('location', 'like', "%{$request->location}%");
        }

        if ($request->boolean('verified_only')) {
            $query->where('is_verified', true);
        }

        if ($request->filled('status')) {
            $query->where('availability_status', $request->status);
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $maids = $query->limit($limit)->offset($offset)->get();
        $total = $query->count();

        $this->logAction('list_maids', [
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $request->only(['location', 'verified_only', 'status']),
        ]);

        return response()->json([
            'data' => $maids,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    // =========================================================================
    // Employer Management
    // =========================================================================

    public function getEmployerPreferences($employer_id)
    {
        $prefs = EmployerPreference::where('user_id', $employer_id)->firstOrFail();

        $this->logAction('get_employer_preferences', ['employer_id' => $employer_id]);

        return response()->json(['data' => $prefs]);
    }

    public function updateEmployerPreferences(Request $request, $employer_id)
    {
        $prefs = EmployerPreference::where('user_id', $employer_id)->firstOrFail();
        $prefs->update($request->only(['schedule', 'budget', 'help_types']));

        $this->logAction('update_employer_preferences', [
            'employer_id' => $employer_id,
            'updates' => $request->only(['schedule', 'budget', 'help_types']),
        ]);

        return response()->json(['message' => 'Preferences updated', 'data' => $prefs]);
    }

    // =========================================================================
    // Booking & Assignment Management
    // =========================================================================

    public function createBooking(Request $request)
    {
        $request->validate([
            'employer_id' => 'required|integer',
            'maid_id' => 'required|integer',
            'service_type' => 'required|string',
        ]);

        $booking = Booking::create($request->all());

        $this->logAction('create_booking', [
            'booking_id' => $booking->id,
            'employer_id' => $request->employer_id,
            'maid_id' => $request->maid_id,
        ]);

        return response()->json(['message' => 'Booking created', 'data' => $booking]);
    }

    public function cancelBooking($booking_id)
    {
        $booking = Booking::findOrFail($booking_id);
        $booking->status = 'cancelled';
        $booking->save();

        $this->logAction('cancel_booking', ['booking_id' => $booking_id]);

        return response()->json(['message' => 'Booking cancelled', 'data' => $booking]);
    }

    public function getUserBookings(Request $request)
    {
        $user_id = $request->user_id;
        $user_type = $request->user_type; // 'employer' or 'maid'

        $bookings = Booking::where($user_type . '_id', $user_id)->get();

        $this->logAction('get_user_bookings', [
            'user_id' => $user_id,
            'user_type' => $user_type,
        ]);

        return response()->json(['data' => $bookings]);
    }

    public function listBookings(Request $request)
    {
        $query = Booking::with(['employer', 'maid']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employer_id')) {
            $query->where('employer_id', $request->employer_id);
        }

        if ($request->filled('maid_id')) {
            $query->where('maid_id', $request->maid_id);
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $bookings = $query->limit($limit)->offset($offset)->get();
        $total = $query->count();

        $this->logAction('list_bookings', [
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $request->only(['status', 'employer_id', 'maid_id']),
        ]);

        return response()->json([
            'data' => $bookings,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    // =========================================================================
    // Assignment Management
    // =========================================================================

    public function listAssignments(Request $request)
    {
        $query = MaidAssignment::with(['employer', 'maid', 'booking']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employer_id')) {
            $query->where('employer_id', $request->employer_id);
        }

        if ($request->filled('maid_id')) {
            $query->where('maid_id', $request->maid_id);
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $assignments = $query->limit($limit)->offset($offset)->get();
        $total = $query->count();

        $this->logAction('list_assignments', [
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $request->only(['status', 'employer_id', 'maid_id']),
        ]);

        return response()->json([
            'data' => $assignments,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function getAssignment($assignment_id)
    {
        $assignment = MaidAssignment::with(['employer', 'maid', 'booking'])->findOrFail($assignment_id);

        $this->logAction('get_assignment', ['assignment_id' => $assignment_id]);

        return response()->json(['data' => $assignment]);
    }

    public function acceptAssignment($assignment_id)
    {
        $assignment = MaidAssignment::findOrFail($assignment_id);
        $assignment->status = 'accepted';
        $assignment->accepted_at = now();
        $assignment->save();

        $this->logAction('accept_assignment', ['assignment_id' => $assignment_id]);

        return response()->json(['message' => 'Assignment accepted', 'data' => $assignment]);
    }

    public function rejectAssignment(Request $request, $assignment_id)
    {
        $assignment = MaidAssignment::findOrFail($assignment_id);
        $assignment->status = 'rejected';
        $assignment->rejected_at = now();
        $assignment->rejection_reason = $request->input('reason');
        $assignment->save();

        $this->logAction('reject_assignment', [
            'assignment_id' => $assignment_id,
            'reason' => $request->input('reason'),
        ]);

        return response()->json(['message' => 'Assignment rejected', 'data' => $assignment]);
    }

    public function completeAssignment($assignment_id)
    {
        $assignment = MaidAssignment::findOrFail($assignment_id);
        $assignment->status = 'completed';
        $assignment->completed_at = now();
        $assignment->save();

        $this->logAction('complete_assignment', ['assignment_id' => $assignment_id]);

        return response()->json(['message' => 'Assignment completed', 'data' => $assignment]);
    }

    public function getAssignmentStatistics()
    {
        $stats = [
            'total' => MaidAssignment::count(),
            'pending' => MaidAssignment::where('status', 'pending')->count(),
            'accepted' => MaidAssignment::where('status', 'accepted')->count(),
            'rejected' => MaidAssignment::where('status', 'rejected')->count(),
            'completed' => MaidAssignment::where('status', 'completed')->count(),
        ];

        $this->logAction('get_assignment_statistics', []);

        return response()->json(['data' => $stats]);
    }

    // =========================================================================
    // Wallet & Payments
    // =========================================================================

    public function getWallet(Request $request)
    {
        $userId = $this->getTargetUserId($request);

        if (!$userId) {
            return response()->json(['error' => 'user_id is required'], 400);
        }

        $employerWallet = DB::table('employer_wallet')->where('user_id', $userId)->first();
        $maidWallet = DB::table('maid_wallet')->where('user_id', $userId)->first();

        $this->logAction('get_wallet', ['user_id' => $userId]);

        return response()->json([
            'data' => [
                'user_id' => $userId,
                'employer_wallet' => $employerWallet,
                'maid_wallet' => $maidWallet,
            ]
        ]);
    }

    public function getWalletTransactions(Request $request)
    {
        $userId = $this->getTargetUserId($request);

        if (!$userId) {
            return response()->json(['error' => 'user_id is required'], 400);
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $transactions = WalletTransaction::where('user_id', $userId)
            ->limit($limit)
            ->offset($offset)
            ->orderBy('created_at', 'desc')
            ->get();

        $total = WalletTransaction::where('user_id', $userId)->count();

        $this->logAction('get_wallet_transactions', [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return response()->json([
            'data' => $transactions,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    // =========================================================================
    // Notifications
    // =========================================================================

    public function listNotifications(Request $request)
    {
        $userId = $this->getTargetUserId($request);

        if (!$userId) {
            return response()->json(['error' => 'user_id is required'], 400);
        }

        $query = Notification::where('user_id', $userId);

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $notifications = $query->limit($limit)->offset($offset)->orderBy('created_at', 'desc')->get();
        $unreadCount = Notification::where('user_id', $userId)->whereNull('read_at')->count();
        $total = $query->count();

        $this->logAction('list_notifications', [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return response()->json([
            'data' => $notifications,
            'total' => $total,
            'unread_count' => $unreadCount,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function getUnreadCount(Request $request)
    {
        $userId = $this->getTargetUserId($request);

        if (!$userId) {
            return response()->json(['error' => 'user_id is required'], 400);
        }

        $count = Notification::where('user_id', $userId)->whereNull('read_at')->count();

        $this->logAction('get_unread_count', ['user_id' => $userId]);

        return response()->json(['count' => $count]);
    }

    public function markNotificationAsRead($notification_id)
    {
        $notification = Notification::findOrFail($notification_id);
        $notification->read_at = now();
        $notification->save();

        $this->logAction('mark_notification_read', ['notification_id' => $notification_id]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllNotificationsAsRead(Request $request)
    {
        $userId = $this->getTargetUserId($request);

        if (!$userId) {
            return response()->json(['error' => 'user_id is required'], 400);
        }

        $count = Notification::where('user_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);

        $this->logAction('mark_all_notifications_read', ['user_id' => $userId, 'count' => $count]);

        return response()->json(['message' => 'All notifications marked as read', 'count' => $count]);
    }

    public function deleteNotification($notification_id)
    {
        $notification = Notification::findOrFail($notification_id);
        $notification->delete();

        $this->logAction('delete_notification', ['notification_id' => $notification_id]);

        return response()->json(['message' => 'Notification deleted']);
    }

    // =========================================================================
    // User Management
    // =========================================================================

    public function listUsers(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $users = $query->limit($limit)->offset($offset)->get();
        $total = $query->count();

        $this->logAction('list_users', [
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $request->only(['role', 'status']),
        ]);

        return response()->json([
            'data' => $users,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function getUser($user_id)
    {
        $user = User::with(['maidProfile', 'employerPreference'])->findOrFail($user_id);

        $this->logAction('get_user', ['user_id' => $user_id]);

        return response()->json(['data' => $user]);
    }

    public function updateUserStatus(Request $request, $user_id)
    {
        $request->validate(['status' => 'required|in:active,inactive,suspended,banned']);

        $user = User::findOrFail($user_id);
        $user->status = $request->status;
        $user->save();

        $this->logAction('update_user_status', [
            'user_id' => $user_id,
            'status' => $request->status,
        ]);

        return response()->json(['message' => 'User status updated', 'data' => $user]);
    }

    // =========================================================================
    // Reference Data
    // =========================================================================

    public function getSkills()
    {
        $skills = DB::table('skills')->get();
        return response()->json(['data' => $skills]);
    }

    public function getHelpTypes()
    {
        $helpTypes = DB::table('help_types')->get();
        return response()->json(['data' => $helpTypes]);
    }
}