<?php

namespace App\Services;

use App\Models\AgentChannelIdentity;
use App\Models\AgentConversation;
use App\Models\AgentLead;
use App\Models\AgentMessage;
use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\DTOs\InboundMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * EmailPoller Service
 * 
 * Polls configured email inboxes for inbound messages from users.
 * Processes emails and routes them through the AmbassadorAgent.
 * 
 * Supported providers:
 * - IMAP (generic)
 * - Mailgun (webhook)
 * - SendGrid (webhook)
 * 
 * Usage:
 *   - Run via Laravel Schedule: $schedule->call(fn() => app(EmailPoller::class)->poll())->everyFiveMinutes();
 *   - Or manually: php artisan email:poll
 */
class EmailPoller
{
    /**
     * Poll all configured email inboxes for new messages.
     */
    public function poll(): array
    {
        $results = [
            'polled_at' => now()->toDateTimeString(),
            'emails_processed' => 0,
            'errors' => [],
        ];

        try {
            // Check Mailgun webhook inbox (primary)
            $results['mailgun'] = $this->pollMailgun();

            // Check IMAP inbox (fallback/backup)
            $results['imap'] = $this->pollImap();

            Log::info('Email poll completed', ['results' => $results]);
        } catch (\Throwable $e) {
            Log::error('Email poll failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Poll Mailgun for inbound emails.
     * In production, this would use Mailgun's API or webhook storage.
     */
    private function pollMailgun(): array
    {
        $processed = 0;

        // In production, you would:
        // 1. Call Mailgun API: GET /domains/{domain}/messages
        // 2. Or use webhook storage to retrieve pending emails
        // 3. Parse each email and route through AmbassadorAgent

        // Placeholder: Log that Mailgun polling is configured
        Log::debug('Mailgun poll: checking for new emails...');

        return [
            'provider' => 'mailgun',
            'processed' => $processed,
            'status' => 'configured',
        ];
    }

    /**
     * Poll IMAP inbox for new emails.
     * Uses PHP's IMAP extension to connect to email server.
     */
    private function pollImap(): array
    {
        $processed = 0;

        $imapHost = $this->getEmailConfig('imap_host');
        $imapPort = $this->getEmailConfig('imap_port', 993);
        $imapUsername = $this->getEmailConfig('imap_username');
        $imapPassword = $this->getEmailConfig('imap_password');

        if (!$imapHost || !$imapUsername || !$imapPassword) {
            return [
                'provider' => 'imap',
                'processed' => 0,
                'status' => 'not_configured',
            ];
        }

        try {
            $mailbox = "{" . $imapHost . ":{$imapPort}/imap/ssl}INBOX";
            $imapStream = @imap_open($mailbox, $imapUsername, $imapPassword);

            if (!$imapStream) {
                throw new \RuntimeException('IMAP connection failed: ' . imap_last_error());
            }

            // Search for unseen emails
            $emails = imap_search($imapStream, 'UNSEEN');

            if ($emails) {
                foreach ($emails as $emailId) {
                    try {
                        $this->processImapEmail($imapStream, $emailId);
                        $processed++;

                        // Mark as seen
                        imap_set_flag_full($imapStream, $emailId, '\\Seen');
                    } catch (\Throwable $e) {
                        Log::error('Failed to process email ID ' . $emailId . ': ' . $e->getMessage());
                    }
                }
            }

            imap_close($imapStream);
        } catch (\Throwable $e) {
            Log::error('IMAP poll failed: ' . $e->getMessage());
        }

        return [
            'provider' => 'imap',
            'processed' => $processed,
            'status' => 'completed',
        ];
    }

    /**
     * Process a single IMAP email through the AmbassadorAgent.
     */
    private function processImapEmail($imapStream, int $emailId): void
    {
        $header = imap_headerinfo($imapStream, $emailId);
        $body = imap_fetchbody($imapStream, $emailId, 1);

        // Decode email body
        if ($header->encoding === 1 || $header->encoding === 4) {
            $body = imap_utf8($body);
        } else {
            $body = imap_base64($body);
        }

        // Extract sender info
        $from = $header->from[0] ?? null;
        $senderEmail = $from->mailbox . '@' . $from->host;
        $senderName = $from->personal ?? $senderEmail;

        // Create inbound message DTO
        $message = new InboundMessage(
            channel: 'email',
            externalId: (string) ($header->message_id ?? uniqid('email_')),
            content: trim($body),
            phone: null,
            email: $senderEmail,
            subject: $header->subject ?? null,
            threadId: $header->message_id ?? null,
        );

        // Route through AmbassadorAgent
        $ambassador = app(AmbassadorAgent::class);
        $response = $ambassador->handle($message);

        Log::info('Email processed through Ambassador', [
            'from' => $senderEmail,
            'subject' => $header->subject,
            'conversation_id' => $response['conversation_id'] ?? null,
        ]);
    }

    /**
     * Process a webhook email from Mailgun/SendGrid.
     * Called by the webhook controller when a new email arrives.
     */
    public function processWebhookEmail(array $payload): array
    {
        $senderEmail = $payload['sender'] ?? $payload['From'] ?? null;
        $subject = $payload['subject'] ?? $payload['Subject'] ?? null;
        $body = $payload['body-plain'] ?? $payload['Body'] ?? $payload['text'] ?? '';
        $messageId = $payload['message-id'] ?? $payload['Message-Id'] ?? uniqid('webhook_');

        if (!$senderEmail) {
            return ['success' => false, 'error' => 'No sender email found in payload'];
        }

        // Create inbound message DTO
        $message = new InboundMessage(
            channel: 'email',
            externalId: $messageId,
            content: trim($body),
            phone: null,
            email: $senderEmail,
            subject: $subject,
            threadId: $messageId,
        );

        // Route through AmbassadorAgent
        $ambassador = app(AmbassadorAgent::class);
        $response = $ambassador->handle($message);

        return [
            'success' => true,
            'conversation_id' => $response['conversation_id'] ?? null,
            'content' => $response['content'] ?? null,
        ];
    }

    /**
     * Get an email configuration value from the database settings table.
     * Falls back to config/services.php for backward compatibility.
     */
    private function getEmailConfig(string $key, mixed $default = null): mixed
    {
        // Try database setting first (admin-configurable via /admin/settings)
        $dbValue = \App\Models\Setting::get("email_{$key}");
        if ($dbValue !== null && $dbValue !== '') {
            return $dbValue;
        }

        // Fallback to config/services.php
        return config("services.email.{$key}", $default);
    }
}
