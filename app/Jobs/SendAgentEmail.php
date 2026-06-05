<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SendAgentEmail Job
 * 
 * Sends an outbound email response from the AmbassadorAgent to a user.
 * Handles email delivery with retry logic and failure tracking.
 * 
 * Usage:
 *   SendAgentEmail::dispatch($content, $toEmail, $conversationId, $subject);
 */
class SendAgentEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job on failure.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = [15, 30, 60];

    /**
     * Maximum seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $content,
        public string $toEmail,
        public ?string $conversationId = null,
        public ?string $subject = null,
        public array $headers = [],
    ) {
        $this->onQueue('email-outbound');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Sending agent email', [
            'to' => $this->toEmail,
            'conversation_id' => $this->conversationId,
            'subject' => $this->subject,
        ]);

        try {
            // Build email content
            $emailSubject = $this->subject ?? 'Re: Your Maids.ng Inquiry';
            $emailBody = $this->buildEmailBody($this->content);
            $toEmail = $this->toEmail;
            $conversationId = $this->conversationId;
            $headers = $this->headers;

            // Send via Laravel's Mail facade
            Mail::raw($emailBody, function ($message) use ($toEmail, $emailSubject, $conversationId, $headers) {
                $message->to($toEmail)
                    ->subject($emailSubject)
                    ->priority(3); // Normal priority

                // Add conversation headers for threading
                if ($conversationId) {
                    $message->getHeaders()->addTextHeader(
                        'X-Maidsng-Conversation-Id',
                        $conversationId
                    );
                }

                // Add any custom headers
                foreach ($headers as $name => $value) {
                    $message->getHeaders()->addTextHeader($name, $value);
                }
            });

            Log::info('Agent email sent successfully', [
                'to' => $this->toEmail,
                'conversation_id' => $this->conversationId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send agent email: ' . $e->getMessage(), [
                'to' => $this->toEmail,
                'conversation_id' => $this->conversationId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the email body with proper formatting.
     */
    private function buildEmailBody(string $content): string
    {
        // Convert markdown-style content to HTML email
        $html = nl2br(e($content));

        return <<<EMAIL
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9fafb; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Maids.ng</h1>
            <p>Your AI Assistant</p>
        </div>
        <div class="content">
            {$html}
        </div>
        <div class="footer">
            <p>This is an automated response from the Maids.ng AI Assistant.</p>
            <p>If you need further assistance, please reply to this email or visit maids.ng</p>
        </div>
    </div>
</body>
</html>
EMAIL;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendAgentEmail job permanently failed', [
            'to' => $this->toEmail,
            'conversation_id' => $this->conversationId,
            'error' => $exception->getMessage(),
        ]);

        // Could add to dead letter queue, notify admin, etc.
    }
}