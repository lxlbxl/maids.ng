<?php

namespace App\Services;

use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Services\Agents\Channels\EmailChannelHandler;
use App\Services\Agents\Channels\WhatsAppChannelHandler;
use Illuminate\Support\Facades\Log;

class ChannelSender
{
    public function send(AgentConversation $conversation, string $message): bool
    {
        return match ($conversation->channel) {
            'email'     => $this->sendEmail($conversation, $message),
            'whatsapp'  => $this->sendWhatsApp($conversation, $message),
            'web'       => $this->sendWeb($conversation, $message),
            'instagram' => $this->sendInstagram($conversation, $message),
            'facebook'  => $this->sendFacebook($conversation, $message),
            default     => $this->sendDefault($conversation, $message),
        };
    }

    private function sendEmail(AgentConversation $conversation, string $message): bool
    {
        try {
            $identity = $conversation->identity;
            \Mail::raw($message, function ($mail) use ($identity, $conversation) {
                $mail->to($identity->email)
                     ->subject('Re: ' . ($conversation->email_subject ?? 'Maids.ng Support'))
                     ->replyTo(config('mail.from.address'), config('mail.from.name'));
            });
            return true;
        } catch (\Exception $e) {
            Log::error("ChannelSender (email): " . $e->getMessage());
            return false;
        }
    }

    private function sendWhatsApp(AgentConversation $conversation, string $message): bool
    {
        try {
            $phoneNumberId = Setting::get('whatsapp_phone_number_id')
                ?? config('services.whatsapp.phone_number_id');

            $response = \Http::withToken(config('services.whatsapp.access_token'))
                ->timeout(10)
                ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $conversation->identity->external_id,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("ChannelSender (whatsapp): " . $e->getMessage());
            return false;
        }
    }

    private function sendWeb(AgentConversation $conversation, string $message): bool
    {
        AgentMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'admin',
            'content'         => $message,
            'created_at'      => now(),
        ]);
        $conversation->update(['last_message_at' => now()]);
        return true;
    }

    private function sendInstagram(AgentConversation $conversation, string $message): bool
    {
        Log::info("ChannelSender (instagram): Instagram send not yet implemented for conversation {$conversation->id}");
        return false;
    }

    private function sendFacebook(AgentConversation $conversation, string $message): bool
    {
        Log::info("ChannelSender (facebook): Facebook send not yet implemented for conversation {$conversation->id}");
        return false;
    }

    private function sendDefault(AgentConversation $conversation, string $message): bool
    {
        Log::warning("ChannelSender: Unknown channel '{$conversation->channel}' for conversation {$conversation->id}");
        return false;
    }
}
