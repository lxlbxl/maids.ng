<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected NotificationLog $notification;

    public function __construct(NotificationLog $notification)
    {
        $this->notification = $notification;
    }

    public function handle(NotificationService $notificationService): void
    {
        if ($this->notification->status !== 'failed') {
            return;
        }

        if ($this->notification->retry_count >= 3) {
            return;
        }

        $result = $notificationService->sendSms(
            $this->notification->user,
            $this->notification->content,
            json_decode($this->notification->context_json ?? '[]', true) ?: [],
            $this->notification->type
        );

        if ($result['success']) {
            $this->notification->markAsSent();
        } else {
            $this->notification->increment('retry_count');
        }
    }
}
