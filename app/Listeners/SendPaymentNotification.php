<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentNotification
{
    /**
     * Send email + WhatsApp notification when payment is confirmed.
     */
    public function handle(PaymentConfirmed $event): void
    {
        $user = $event->user;
        $amount = number_format($event->amount);

        // ── Email Notification ──
        try {
            if ($user->email) {
                Mail::raw(
                    "Hello {$user->name},\n\n" .
                    "Your payment of ₦{$amount} has been confirmed.\n\n" .
                    "Reference: {$event->reference}\n" .
                    "Type: {$event->type}\n\n" .
                    "We are now processing your matching request. Our AI matching engine will find the best domestic staff matches based on your preferences.\n\n" .
                    "You can track your matches at: " . config('app.url') . "/dashboard\n\n" .
                    "If you have any questions, reply to us on WhatsApp or email support@maids.ng.\n\n" .
                    "— The Maids.ng Team",
                    function ($message) use ($user, $event) {
                        $message->to($user->email, $user->name)
                            ->subject('Payment Confirmed — Maids.ng (Ref: ' . $event->reference . ')');
                    }
                );

                Log::info('Payment confirmation email sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'reference' => $event->reference,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send payment confirmation email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // ── WhatsApp Notification ──
        // The Paperclip agent polls payment status and sends the WhatsApp
        // confirmation in-chat. This listener also dispatches an SMS fallback
        // via the configured SMS provider if the user hasn't responded on WhatsApp.
        try {
            if ($user->phone && $event->type === 'matching_fee') {
                $smsProvider = app(SmsProviderInterface::class);
                $smsProvider->send(
                    $user->phone,
                    "Maids.ng: Your matching fee payment of ₦{$amount} has been confirmed. " .
                    "We'll start matching you with verified domestic staff. Track: " . config('app.url') . "/dashboard"
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send payment SMS notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
