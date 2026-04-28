<?php

namespace App\Services;

use App\Models\EmployerWallet;
use App\Models\MaidAssignment;
use App\Models\MaidWallet;
use App\Models\NotificationLog;
use App\Models\SalarySchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SmartNotificationService
{
    /**
     * Work hours configuration (8 AM - 8 PM).
     */
    protected array $workHours = [
        'start' => 8,
        'end' => 20,
    ];

    /**
     * Send notification to maid when assigned (via direct selection or acceptance).
     */
    public function sendMaidAssignmentNotification(MaidAssignment $assignment): ?NotificationLog
    {
        $maid = $assignment->maid;
        $employer = $assignment->employer;

        if (!$maid || !$employer) {
            return null;
        }

        $message = $this->buildMaidAssignmentMessage($assignment, $employer);

        return $this->scheduleNotification(
            recipientId: $maid->id,
            recipientType: 'maid',
            channel: 'sms',
            notificationType: 'assignment_new',
            message: $message,
            referenceId: $assignment->id,
            referenceType: 'assignment',
            context: [
                'assignment_id' => $assignment->id,
                'employer_id' => $employer->id,
                'employer_name' => $employer->name,
                'job_location' => $assignment->job_location,
                'salary_amount' => $assignment->salary_amount,
                'salary_currency' => $assignment->salary_currency,
                'guarantee_period_days' => $assignment->guarantee_period_days,
            ]
        );
    }

    /**
     * Send notification to employer when maid is assigned (guarantee match).
     */
    public function sendEmployerAssignmentNotification(MaidAssignment $assignment): ?NotificationLog
    {
        $employer = $assignment->employer;
        $maid = $assignment->maid;

        if (!$employer || !$maid) {
            return null;
        }

        $message = $this->buildEmployerAssignmentMessage($assignment, $maid);

        // Send immediately via multiple channels
        $this->sendImmediateNotification(
            recipientId: $employer->id,
            recipientType: 'employer',
            channels: ['email', 'push'],
            notificationType: 'assignment_pending_acceptance',
            message: $message,
            referenceId: $assignment->id,
            referenceType: 'assignment',
            context: [
                'assignment_id' => $assignment->id,
                'maid_id' => $maid->id,
                'maid_name' => $maid->name,
                'ai_match_score' => $assignment->ai_match_score,
                'ai_match_reasoning' => $assignment->ai_match_reasoning,
                'guarantee_period_days' => $assignment->guarantee_period_days,
            ]
        );

        // Also schedule SMS for work hours
        return $this->scheduleNotification(
            recipientId: $employer->id,
            recipientType: 'employer',
            channel: 'sms',
            notificationType: 'assignment_pending_acceptance',
            message: $message,
            referenceId: $assignment->id,
            referenceType: 'assignment',
            context: [
                'assignment_id' => $assignment->id,
                'maid_id' => $maid->id,
                'maid_name' => $maid->name,
                'requires_action' => true,
                'action_url' => '/employer/assignments/' . $assignment->id,
            ]
        );
    }

    /**
     * Send notification when assignment is rejected.
     */
    public function sendAssignmentRejectionNotification(MaidAssignment $assignment, string $reason): ?NotificationLog
    {
        $employer = $assignment->employer;

        if (!$employer) {
            return null;
        }

        $message = "Your maid assignment has been rejected. ";
        if ($assignment->isGuaranteeMatch()) {
            $message .= "We're finding a replacement for you. ";
        }
        $message .= "Reason: " . ($reason ?: 'Not specified') . ". Refund processed to your wallet.";

        return $this->scheduleNotification(
            recipientId: $employer->id,
            recipientType: 'employer',
            channel: 'sms',
            notificationType: 'assignment_rejected',
            message: $message,
            referenceId: $assignment->id,
            referenceType: 'assignment',
            context: [
                'assignment_id' => $assignment->id,
                'rejection_reason' => $reason,
                'refund_amount' => $assignment->matching_fee_amount,
                'is_guarantee_match' => $assignment->isGuaranteeMatch(),
                'replacement_search_initiated' => $assignment->isGuaranteeMatch(),
            ]
        );
    }

    /**
     * Send notification when assignment is completed.
     */
    public function sendAssignmentCompletionNotification(MaidAssignment $assignment): void
    {
        $employer = $assignment->employer;
        $maid = $assignment->maid;

        if ($employer) {
            $this->scheduleNotification(
                recipientId: $employer->id,
                recipientType: 'employer',
                channel: 'sms',
                notificationType: 'assignment_completed',
                message: "Your maid assignment with {$maid?->name} has been completed. Thank you for using Maids.ng!",
                referenceId: $assignment->id,
                referenceType: 'assignment',
                context: [
                    'assignment_id' => $assignment->id,
                    'completion_date' => now()->toDateString(),
                ]
            );
        }

        if ($maid) {
            $this->scheduleNotification(
                recipientId: $maid->id,
                recipientType: 'maid',
                channel: 'sms',
                notificationType: 'assignment_completed',
                message: "Your assignment with {$employer?->name} has been completed. You are now available for new assignments.",
                referenceId: $assignment->id,
                referenceType: 'assignment',
                context: [
                    'assignment_id' => $assignment->id,
                    'completion_date' => now()->toDateString(),
                ]
            );
        }
    }

    /**
     * Send notification when assignment is cancelled.
     */
    public function sendAssignmentCancellationNotification(MaidAssignment $assignment, string $reason): void
    {
        $employer = $assignment->employer;
        $maid = $assignment->maid;

        if ($employer) {
            $this->scheduleNotification(
                recipientId: $employer->id,
                recipientType: 'employer',
                channel: 'sms',
                notificationType: 'assignment_cancelled',
                message: "Your maid assignment has been cancelled. Reason: " . ($reason ?: 'Not specified'),
                referenceId: $assignment->id,
                referenceType: 'assignment',
                context: [
                    'assignment_id' => $assignment->id,
                    'cancellation_reason' => $reason,
                ]
            );
        }

        if ($maid) {
            $this->scheduleNotification(
                recipientId: $maid->id,
                recipientType: 'maid',
                channel: 'sms',
                notificationType: 'assignment_cancelled',
                message: "Your assignment has been cancelled. You are now available for new assignments.",
                referenceId: $assignment->id,
                referenceType: 'assignment',
                context: [
                    'assignment_id' => $assignment->id,
                    'cancellation_reason' => $reason,
                ]
            );
        }
    }

    /**
     * Send salary reminder to employer (3 days before due).
     */
    public function sendSalaryReminder3Days(SalarySchedule $schedule): ?NotificationLog
    {
        $employer = $schedule->employer;

        if (!$employer) {
            return null;
        }

        $dueDate = $schedule->next_salary_due_date;
        $message = "Reminder: Salary payment of {$schedule->salary_currency} {$schedule->monthly_salary} for your maid is due in 3 days (" . $dueDate->format('M d') . "). Please fund your wallet.";

        $notification = $this->scheduleNotification(
            recipientId: $employer->id,
            recipientType: 'employer',
            channel: 'sms',
            notificationType: 'salary_reminder_3_days',
            message: $message,
            referenceId: $schedule->id,
            referenceType: 'salary_schedule',
            context: [
                'salary_schedule_id' => $schedule->id,
                'due_date' => $dueDate->toDateString(),
                'salary_amount' => $schedule->monthly_salary,
                'days_until_due' => 3,
                'wallet_balance' => $this->getEmployerWalletBalance($employer->id),
            ]
        );

        if ($notification) {
            $schedule->markReminderSent();
        }

        return $notification;
    }

    /**
     * Send salary reminder to employer (1 day before due).
     */
    public function sendSalaryReminder1Day(SalarySchedule $schedule): ?NotificationLog
    {
        $employer = $schedule->employer;

        if (!$employer) {
            return null;
        }

        $dueDate = $schedule->next_salary_due_date;
        $message = "Urgent: Salary payment of {$schedule->salary_currency} {$schedule->monthly_salary} is due tomorrow (" . $dueDate->format('M d') . "). Please ensure your wallet is funded.";

        $notification = $this->scheduleNotification(
            recipientId: $employer->id,
            recipientType: 'employer',
            channel: 'sms',
            notificationType: 'salary_reminder_1_day',
            message: $message,
            referenceId: $schedule->id,
            referenceType: 'salary_schedule',
            context: [
                'salary_schedule_id' => $schedule->id,
                'due_date' => $dueDate->toDateString(),
                'salary_amount' => $schedule->monthly_salary,
                'days_until_due' => 1,
                'wallet_balance' => $this->getEmployerWalletBalance($employer->id),
            ]
        );

        if ($notification) {
            $schedule->markReminderSent();
        }

        return $notification;
    }

    /**
     * Send salary due notification to employer.
     */
    public function sendSalaryDueNotification(SalarySchedule $schedule): ?NotificationLog
    {
        $employer = $schedule->employer;

        if (!$employer) {
            return null;
        }

        $dueDate = $schedule->next_salary_due_date;
        $message = "Salary payment of {$schedule->salary_currency} {$schedule->monthly_salary} is due today. Processing payment from your wallet.";

        $notification = $this->scheduleNotification(
            recipientId: $employer->id,
            recipientType: 'employer',
            channel: 'sms',
            notificationType: 'salary_due',
            message: $message,
            referenceId: $schedule->id,
            referenceType: 'salary_schedule',
            context: [
                'salary_schedule_id' => $schedule->id,
                'due_date' => $dueDate->toDateString(),
                'salary_amount' => $schedule->monthly_salary,
                'wallet_balance' => $this->getEmployerWalletBalance($employer->id),
            ]
        );

        if ($notification) {
            $schedule->markReminderSent();
        }

        return $notification;
    }

    /**
     * Send salary received notification to maid.
     */
    public function sendSalaryReceivedNotification(SalarySchedule $schedule): ?NotificationLog
    {
        $maid = $schedule->maid;

        if (!$maid) {
            return null;
        }

        $periodEnd = $schedule->current_period_end ?? $schedule->next_salary_due_date;
        $periodStart = $schedule->current_period_start ?? $schedule->next_salary_due_date?->copy()->subMonth();
        $message = "Good news! Your salary of {$schedule->salary_currency} {$schedule->monthly_salary} for the period ending " . $periodEnd->format('M d') . " has been credited to your wallet.";

        return $this->scheduleNotification(
            recipientId: $maid->id,
            recipientType: 'maid',
            channel: 'sms',
            notificationType: 'salary_received',
            message: $message,
            referenceId: $schedule->id,
            referenceType: 'salary_schedule',
            context: [
                'salary_schedule_id' => $schedule->id,
                'salary_amount' => $schedule->monthly_salary,
                'period_start' => $periodStart?->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ]
        );
    }

    /**
     * Send follow-up notification based on previous context.
     */
    public function sendFollowUpNotification(int $parentNotificationId, string $newMessage): ?NotificationLog
    {
        $parentNotification = NotificationLog::find($parentNotificationId);

        if (!$parentNotification) {
            return null;
        }

        $followUpSequence = ($parentNotification->follow_up_sequence ?? 0) + 1;

        $context = $parentNotification->context_json ?? [];
        $context['follow_up_sequence'] = $followUpSequence;
        $context['parent_notification_id'] = $parentNotificationId;
        $context['previous_notification_type'] = $parentNotification->notification_type;
        $context['previous_sent_at'] = $parentNotification->sent_at?->toIso8601String();

        return $this->scheduleNotification(
            recipientId: $parentNotification->user_id,
            recipientType: $parentNotification->user_type,
            channel: $parentNotification->channel,
            notificationType: $parentNotification->notification_type . '_follow_up',
            message: $newMessage,
            referenceId: $parentNotification->reference_id,
            referenceType: $parentNotification->reference_type,
            context: $context,
            parentNotificationId: $parentNotificationId,
            followUpSequence: $followUpSequence
        );
    }

    /**
     * Schedule a notification for delivery during work hours.
     */
    public function scheduleNotification(
        int $recipientId,
        string $recipientType,
        string $channel,
        string $notificationType,
        string $message,
        ?int $referenceId = null,
        string $referenceType = '',
        array $context = [],
        ?int $parentNotificationId = null,
        int $followUpSequence = 0
    ): NotificationLog {
        $timezone = $this->getRecipientTimezone($recipientId, $recipientType);
        $scheduledAt = $this->calculateScheduledTime($timezone);

        $notification = NotificationLog::create([
            'user_id' => $recipientId,
            'user_type' => $recipientType,
            'channel' => $channel,
            'notification_type' => $notificationType,
            'content' => $message,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'context_json' => $context,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'timezone' => $timezone,
            'parent_notification_id' => $parentNotificationId,
            'follow_up_sequence' => $followUpSequence,
        ]);

        Log::info('Notification scheduled', [
            'notification_id' => $notification->id,
            'user_id' => $recipientId,
            'user_type' => $recipientType,
            'scheduled_at' => $scheduledAt,
            'timezone' => $timezone,
        ]);

        return $notification;
    }

    /**
     * Send immediate notification (bypass work hours check).
     */
    protected function sendImmediateNotification(
        int $recipientId,
        string $recipientType,
        array $channels,
        string $notificationType,
        string $message,
        ?int $referenceId = null,
        string $referenceType = '',
        array $context = []
    ): void {
        foreach ($channels as $channel) {
            NotificationLog::create([
                'user_id' => $recipientId,
                'user_type' => $recipientType,
                'channel' => $channel,
                'notification_type' => $notificationType,
                'content' => $message,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
                'context_json' => $context,
                'status' => 'sent',
                'sent_at' => now(),
                'timezone' => $this->getRecipientTimezone($recipientId, $recipientType),
            ]);
        }
    }

    /**
     * Process pending notifications that are due for delivery.
     */
    public function processPendingNotifications(int $batchSize = 100): int
    {
        $notifications = NotificationLog::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->limit($batchSize)
            ->get();

        $processed = 0;

        foreach ($notifications as $notification) {
            try {
                $this->deliverNotification($notification);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to deliver notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);

                $notification->markAsFailed($e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Deliver a notification via the appropriate channel.
     */
    protected function deliverNotification(NotificationLog $notification): void
    {
        $success = false;

        switch ($notification->channel) {
            case 'sms':
                $success = $this->sendSms($notification);
                break;
            case 'email':
                $success = $this->sendEmail($notification);
                break;
            case 'push':
                $success = $this->sendPushNotification($notification);
                break;
            case 'in_app':
                $success = $this->sendInAppNotification($notification);
                break;
        }

        if ($success) {
            $notification->markAsSent();
        } else {
            $notification->markAsFailed('Delivery failed');
        }
    }

    /**
     * Send SMS notification.
     */
    protected function sendSms(NotificationLog $notification): bool
    {
        $user = User::find($notification->user_id);
        if (!$user || !$user->phone) {
            return false;
        }

        $notificationService = app(NotificationService::class);
        $result = $notificationService->sendSms(
            $user,
            $notification->content,
            $notification->context_json ?? [],
            $notification->notification_type
        );

        return $result['success'] ?? false;
    }

    /**
     * Send email notification.
     */
    protected function sendEmail(NotificationLog $notification): bool
    {
        $user = User::find($notification->user_id);
        if (!$user || !$user->email) {
            return false;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw($notification->content, function ($message) use ($user, $notification) {
                $message->to($user->email)
                    ->subject($notification->notification_type);
            });
            return true;
        } catch (\Exception $e) {
            Log::error('Email send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send push notification.
     */
    protected function sendPushNotification(NotificationLog $notification): bool
    {
        // TODO: Implement actual push notification via Firebase/APNs
        Log::info('Push notification queued', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
        ]);
        return true;
    }

    /**
     * Send in-app notification.
     */
    protected function sendInAppNotification(NotificationLog $notification): bool
    {
        // In-app notifications are already stored in NotificationLog
        return true;
    }

    /**
     * Calculate the next scheduled time within work hours.
     */
    protected function calculateScheduledTime(string $timezone): Carbon
    {
        $now = now()->setTimezone($timezone);
        $scheduled = $now->copy();

        // Check if current time is within work hours
        $hour = (int) $scheduled->format('G');

        if ($hour < $this->workHours['start']) {
            // Before work hours, schedule for today at start time
            $scheduled->setTime($this->workHours['start'], 0, 0);
        } elseif ($hour >= $this->workHours['end']) {
            // After work hours, schedule for tomorrow at start time
            $scheduled->addDay()->setTime($this->workHours['start'], 0, 0);
        }
        // If within work hours, send immediately (already set to now)

        return $scheduled->setTimezone(config('app.timezone'));
    }

    /**
     * Get recipient's timezone.
     */
    protected function getRecipientTimezone(int $recipientId, string $recipientType): string
    {
        if ($recipientType === 'employer') {
            $wallet = EmployerWallet::where('employer_id', $recipientId)->first();
            return $wallet?->timezone ?? config('app.timezone', 'Africa/Lagos');
        }

        if ($recipientType === 'maid') {
            // Maids might not have timezone in wallet, use default
            return config('app.timezone', 'Africa/Lagos');
        }

        return config('app.timezone', 'Africa/Lagos');
    }

    /**
     * Get employer wallet balance for context.
     */
    protected function getEmployerWalletBalance(int $employerId): float
    {
        $wallet = EmployerWallet::where('employer_id', $employerId)->first();
        return $wallet?->balance ?? 0;
    }

    /**
     * Build assignment message for maid.
     */
    protected function buildMaidAssignmentMessage(MaidAssignment $assignment, User $employer): string
    {
        $message = "Hello! You have been assigned to work with {$employer->name}. ";

        if ($assignment->job_location) {
            $message .= "Location: {$assignment->job_location}. ";
        }

        if ($assignment->salary_amount) {
            $message .= "Salary: {$assignment->salary_currency} {$assignment->salary_amount}. ";
        }

        $message .= "Please confirm your availability by replying YES or call us for more details.";

        return $message;
    }

    /**
     * Build assignment message for employer.
     */
    protected function buildEmployerAssignmentMessage(MaidAssignment $assignment, User $maid): string
    {
        $message = "Great news! We've found a match for you: {$maid->name}. ";

        if ($assignment->ai_match_score) {
            $message .= "Match score: " . round($assignment->ai_match_score * 100) . "%. ";
        }

        $message .= "Please log in to your account to review and accept or reject this match. ";
        $message .= "You have 48 hours to respond.";

        return $message;
    }

    /**
     * Get notification history for a user.
     */
    public function getNotificationHistory(int $userId, string $userType, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationLog::forRecipient($userId, $userType)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending notifications for a user.
     */
    public function getPendingNotifications(int $userId, string $userType): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationLog::forRecipient($userId, $userType)
            ->pending()
            ->orderBy('scheduled_at', 'asc')
            ->get();
    }

    /**
     * Get notification statistics.
     */
    public function getNotificationStatistics(): array
    {
        return [
            'total' => NotificationLog::count(),
            'pending' => NotificationLog::pending()->count(),
            'sent' => NotificationLog::sent()->count(),
            'failed' => NotificationLog::failed()->count(),
            'by_channel' => [
                'sms' => NotificationLog::byChannel('sms')->count(),
                'email' => NotificationLog::byChannel('email')->count(),
                'push' => NotificationLog::byChannel('push')->count(),
                'in_app' => NotificationLog::byChannel('in_app')->count(),
            ],
            'by_type' => NotificationLog::selectRaw('notification_type, COUNT(*) as count')
                ->groupBy('notification_type')
                ->pluck('count', 'notification_type')
                ->toArray(),
        ];
    }

    /**
     * Retry failed notifications.
     */
    public function retryFailedNotifications(int $batchSize = 50): int
    {
        $notifications = NotificationLog::failed()
            ->where('retry_count', '<', 3)
            ->limit($batchSize)
            ->get();

        $retried = 0;

        foreach ($notifications as $notification) {
            $notification->update([
                'status' => 'pending',
                'retry_count' => $notification->retry_count + 1,
                'scheduled_at' => $this->calculateScheduledTime($notification->timezone),
            ]);
            $retried++;
        }

        return $retried;
    }
}
