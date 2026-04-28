<?php

namespace App\Services;

use App\Models\User;
use App\Models\NotificationLog;
use App\Services\Sms\SmsProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected SmsProviderInterface $smsProvider;
    protected int $workHoursStart = 8;  // 8 AM
    protected int $workHoursEnd = 20;   // 8 PM

    public function __construct(SmsProviderInterface $smsProvider)
    {
        $this->smsProvider = $smsProvider;
    }

    /**
     * Send SMS notification with context logging.
     */
    public function sendSms(User $user, string $message, array $context = [], string $type = 'general'): array
    {
        $log = $this->logNotification($user, 'sms', $message, $context, $type);

        // Check if within work hours (8 AM - 8 PM)
        if (! $this->isWithinWorkHours()) {
            $scheduledTime = $this->getNextWorkHourStart();
            $log->update([
                'status'       => 'scheduled',
                'scheduled_at' => $scheduledTime,
            ]);

            return [
                'success'      => false,
                'scheduled'    => true,
                'scheduled_at' => $scheduledTime,
                'message'      => 'Message scheduled for next work hours',
            ];
        }

        try {
            $result = $this->smsProvider->send($user->phone, $message);

            if ($result['success']) {
                $log->update([
                    'status'            => 'sent',
                    'sent_at'           => now(),
                    'delivery_response' => $result['response'] ?? null,
                ]);

                return [
                    'success' => true,
                    'log_id'  => $log->id,
                    'message' => 'SMS sent successfully',
                ];
            }

            $log->update([
                'status'            => 'failed',
                'delivery_response' => ['error' => $result['error'] ?? 'Unknown error'],
            ]);

            return [
                'success' => false,
                'error'   => $result['error'] ?? 'Failed to send SMS',
                'log_id'  => $log->id,
            ];
        } catch (\Exception $e) {
            $log->update([
                'status'            => 'failed',
                'delivery_response' => ['error' => $e->getMessage()],
            ]);

            Log::error('SMS sending failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'log_id'  => $log->id,
            ];
        }
    }

    /**
     * Send a templated SMS using config/sms.php templates.
     */
    public function sendTemplate(User $user, string $templateKey, array $params = [], string $type = 'general'): array
    {
        $template = config("sms.templates.{$templateKey}");

        if (! $template) {
            Log::warning("SMS template not found: {$templateKey}");
            return ['success' => false, 'error' => "SMS template '{$templateKey}' not found"];
        }

        // Replace placeholders
        $message = $template;
        foreach ($params as $key => $value) {
            $message = str_replace('{' . $key . '}', (string) $value, $message);
        }

        // Add platform default
        $message = str_replace('{platform}', config('app.name', 'Maids.ng'), $message);
        $message = str_replace('{url}', config('app.url', ''), $message);

        return $this->sendSms($user, $message, array_merge($params, ['template' => $templateKey]), $type);
    }

    /**
     * Schedule SMS for later delivery.
     */
    public function scheduleSms(User $user, string $message, Carbon $scheduledAt, array $context = [], string $type = 'general'): NotificationLog
    {
        if (! $this->isWithinWorkHours($scheduledAt)) {
            $scheduledAt = $this->getNextWorkHourStart($scheduledAt);
        }

        return $this->logNotification($user, 'sms', $message, $context, $type, 'scheduled', $scheduledAt);
    }

    /**
     * Send notification to multiple users.
     */
    public function sendBulkSms(array $users, string $message, array $context = [], string $type = 'general'): array
    {
        $results = [];

        foreach ($users as $user) {
            $results[$user->id] = $this->sendSms($user, $message, $context, $type);
        }

        return $results;
    }

    /**
     * Log notification with context for AI follow-ups.
     */
    protected function logNotification(
        User $user,
        string $channel,
        string $content,
        array $context = [],
        string $type = 'general',
        string $status = 'pending',
        ?Carbon $scheduledAt = null
    ): NotificationLog {
        return NotificationLog::create([
            'user_id'           => $user->id,
            'user_type'         => $user->role ?? 'unknown',
            'notification_type' => $type,
            'channel'           => $channel,
            'content'           => $content,
            'context_json'      => $context,
            'status'            => $status,
            'scheduled_at'      => $scheduledAt,
            'timezone'          => config('app.timezone', 'Africa/Lagos'),
        ]);
    }

    /**
     * Get notification history for user.
     */
    public function getUserNotificationHistory(User $user, int $limit = 50): array
    {
        return NotificationLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'type'       => $log->notification_type,
                'channel'    => $log->channel,
                'content'    => $log->content,
                'context'    => $log->context_json,
                'status'     => $log->status,
                'sent_at'    => $log->sent_at,
                'created_at' => $log->created_at,
            ])
            ->toArray();
    }

    /**
     * Get context for AI follow-up.
     */
    public function getContextForAiFollowUp(User $user, ?string $type = null): array
    {
        $query = NotificationLog::where('user_id', $user->id)
            ->whereIn('status', ['sent', 'delivered'])
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('notification_type', $type);
        }

        $logs = $query->limit(10)->get();

        return [
            'user_id'              => $user->id,
            'user_name'            => $user->name,
            'user_role'            => $user->role,
            'recent_notifications' => $logs->map(fn ($log) => [
                'type'    => $log->notification_type,
                'content' => $log->content,
                'context' => $log->context_json,
                'sent_at' => $log->sent_at,
            ])->toArray(),
        ];
    }

    /**
     * Check if current time is within work hours.
     */
    public function isWithinWorkHours(?Carbon $time = null): bool
    {
        $time = $time ?? now();
        $hour = (int) $time->format('H');

        return $hour >= $this->workHoursStart && $hour < $this->workHoursEnd;
    }

    /**
     * Get next work hour start time.
     */
    public function getNextWorkHourStart(?Carbon $from = null): Carbon
    {
        $from = $from ?? now();
        $nextStart = $from->copy()->setTime($this->workHoursStart, 0, 0);

        if ($from->hour >= $this->workHoursEnd) {
            $nextStart->addDay();
        }

        return $nextStart;
    }

    /**
     * Process pending scheduled notifications.
     */
    public function processScheduledNotifications(int $batchSize = 100): array
    {
        $logs = NotificationLog::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->where('scheduled_at', '>=', now()->subDay())
            ->limit($batchSize)
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($logs as $log) {
            if (! $this->isWithinWorkHours()) {
                $log->update(['scheduled_at' => $this->getNextWorkHourStart()]);
                continue;
            }

            $user = User::find($log->user_id);
            if (! $user) {
                $log->update(['status' => 'failed', 'delivery_response' => ['error' => 'User not found']]);
                $failed++;
                continue;
            }

            try {
                $result = $this->smsProvider->send($user->phone, $log->content);

                if ($result['success']) {
                    $log->update([
                        'status'            => 'sent',
                        'sent_at'           => now(),
                        'delivery_response' => $result['response'] ?? null,
                    ]);
                    $processed++;
                } else {
                    $log->update([
                        'status'            => 'failed',
                        'delivery_response' => ['error' => $result['error'] ?? 'Unknown'],
                    ]);
                    $failed++;
                }
            } catch (\Exception $e) {
                $log->update([
                    'status'            => 'failed',
                    'delivery_response' => ['error' => $e->getMessage()],
                ]);
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed, 'total' => $logs->count()];
    }

    /**
     * Retry failed notifications.
     */
    public function retryFailedNotifications(int $maxRetries = 3, int $batchSize = 50): array
    {
        $logs = NotificationLog::where('status', 'failed')
            ->where('follow_up_sequence', '<', $maxRetries)
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->limit($batchSize)
            ->get();

        $retried = 0;
        $succeeded = 0;

        foreach ($logs as $log) {
            $user = User::find($log->user_id);
            if (! $user) {
                continue;
            }

            try {
                $result = $this->smsProvider->send($user->phone, $log->content);

                if ($result['success']) {
                    $log->update([
                        'status'            => 'sent',
                        'sent_at'           => now(),
                        'delivery_response' => $result['response'] ?? null,
                    ]);
                    $succeeded++;
                } else {
                    $log->increment('follow_up_sequence');
                }
                $retried++;
            } catch (\Exception $e) {
                $log->increment('follow_up_sequence');
                $retried++;
            }
        }

        return ['retried' => $retried, 'succeeded' => $succeeded, 'total' => $logs->count()];
    }

    /**
     * Get the active SMS provider instance.
     */
    public function getProvider(): SmsProviderInterface
    {
        return $this->smsProvider;
    }

    /**
     * Get notification statistics.
     */
    public function getStatistics(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subDays(30);
        $to   = $to ?? now();

        $query = NotificationLog::whereBetween('created_at', [$from, $to]);

        return [
            'total'      => (clone $query)->count(),
            'sent'       => (clone $query)->where('status', 'sent')->count(),
            'failed'     => (clone $query)->where('status', 'failed')->count(),
            'scheduled'  => (clone $query)->where('status', 'scheduled')->count(),
            'pending'    => (clone $query)->where('status', 'pending')->count(),
            'by_type'    => NotificationLog::whereBetween('created_at', [$from, $to])
                ->selectRaw('notification_type, COUNT(*) as count')
                ->groupBy('notification_type')
                ->pluck('count', 'notification_type')
                ->toArray(),
            'by_channel' => NotificationLog::whereBetween('created_at', [$from, $to])
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
        ];
    }
}
