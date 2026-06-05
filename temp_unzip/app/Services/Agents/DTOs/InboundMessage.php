<?php

namespace App\Services\Agents\DTOs;

class InboundMessage
{
    public function __construct(
        public readonly string $channel,
        public readonly string $externalId,
        public readonly string $content,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $subject = null,
        public readonly ?string $threadId = null,
        public readonly ?string $externalMessageId = null,
        public readonly array $metadata = [],
    ) {
    }

    public static function fromWeb(array $payload): self
    {
        return new self(
            channel: 'web',
            externalId: $payload['session_id'] ?? uniqid('web_'),
            content: $payload['message'] ?? '',
            phone: $payload['phone'] ?? null,
            email: $payload['email'] ?? null,
            externalMessageId: $payload['message_id'] ?? null,
            metadata: $payload['metadata'] ?? [],
        );
    }

    public static function fromEmail(array $payload): self
    {
        return new self(
            channel: 'email',
            externalId: $payload['from'] ?? '',
            content: $payload['body'] ?? '',
            email: $payload['from'] ?? null,
            subject: $payload['subject'] ?? null,
            threadId: $payload['thread_id'] ?? null,
            externalMessageId: $payload['message_id'] ?? null,
        );
    }

    public static function fromWhatsApp(array $payload): self
    {
        return new self(
            channel: 'whatsapp',
            externalId: $payload['from'] ?? '',
            content: $payload['body'] ?? '',
            phone: $payload['from'] ?? null,
            externalMessageId: $payload['message_id'] ?? null,
        );
    }

    public static function fromInstagram(array $payload): self
    {
        return new self(
            channel: 'instagram',
            externalId: $payload['sender_id'] ?? '',
            content: $payload['message'] ?? '',
            externalMessageId: $payload['mid'] ?? null,
        );
    }

    public static function fromFacebook(array $payload): self
    {
        return new self(
            channel: 'facebook',
            externalId: $payload['sender_id'] ?? '',
            content: $payload['message'] ?? '',
            externalMessageId: $payload['mid'] ?? null,
        );
    }
}