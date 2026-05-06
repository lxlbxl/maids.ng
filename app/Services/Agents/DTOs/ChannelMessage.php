<?php

namespace App\Services\Agents\DTOs;

/**
 * ChannelMessage — unified inbound message from any channel.
 *
 * All channels (web, email, WhatsApp, Instagram, Facebook) map to this single format.
 */
class ChannelMessage
{
    public function __construct(
        public readonly string $channel,
        public readonly ?string $externalMessageId,
        public readonly string $content,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $name = null,
        public readonly ?string $subject = null,
        public readonly ?string $threadId = null,
        public readonly ?array $attachments = null,
        public readonly ?int $userId = null,
        public readonly ?string $tier = null,
    ) {
    }

    public static function fromWeb(array $payload): self
    {
        return new self(
            channel: 'web',
            externalMessageId: $payload['external_message_id'] ?? null,
            content: $payload['content'],
            phone: $payload['phone'] ?? null,
            email: $payload['email'] ?? null,
            name: $payload['name'] ?? null,
            userId: $payload['user_id'] ?? null,
            tier: $payload['tier'] ?? 'guest',
        );
    }

    public static function fromEmail(array $payload): self
    {
        return new self(
            channel: 'email',
            externalMessageId: $payload['message_id'] ?? null,
            content: $payload['body'] ?? $payload['content'],
            phone: null,
            email: $payload['from'] ?? null,
            name: $payload['from_name'] ?? null,
            subject: $payload['subject'] ?? null,
            threadId: $payload['thread_id'] ?? null,
            attachments: $payload['attachments'] ?? null,
            userId: $payload['user_id'] ?? null,
            tier: $payload['tier'] ?? 'guest',
        );
    }

    public static function fromWhatsApp(array $payload): self
    {
        return new self(
            channel: 'whatsapp',
            externalMessageId: $payload['message_sid'] ?? $payload['messageId'] ?? null,
            content: $payload['body'] ?? $payload['text'] ?? '',
            phone: $payload['from'] ?? $payload['phone'] ?? null,
            email: null,
            name: $payload['name'] ?? null,
            userId: $payload['user_id'] ?? null,
            tier: $payload['tier'] ?? 'guest',
        );
    }

    public static function fromInstagram(array $payload): self
    {
        return new self(
            channel: 'instagram',
            externalMessageId: $payload['message_id'] ?? $payload['mid'] ?? null,
            content: $payload['text'] ?? $payload['message'] ?? '',
            phone: null,
            email: null,
            name: $payload['sender_name'] ?? null,
            userId: $payload['user_id'] ?? null,
            tier: $payload['tier'] ?? 'guest',
        );
    }

    public static function fromFacebook(array $payload): self
    {
        return new self(
            channel: 'facebook',
            externalMessageId: $payload['message_id'] ?? $payload['mid'] ?? null,
            content: $payload['text'] ?? $payload['message'] ?? '',
            phone: null,
            email: null,
            name: $payload['sender_name'] ?? null,
            userId: $payload['user_id'] ?? null,
            tier: $payload['tier'] ?? 'guest',
        );
    }

    public function getTier(): string
    {
        return $this->tier ?? 'guest';
    }
}