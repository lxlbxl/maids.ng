<?php

namespace App\Services\Sms;

/**
 * Contract for SMS sending providers.
 *
 * All SMS providers (Termii, Twilio, Africa's Talking, Log)
 * must implement this interface so the NotificationService
 * can swap providers without code changes.
 */
interface SmsProviderInterface
{
    /**
     * Send an SMS message.
     *
     * @param  string  $phone   Recipient phone number (E.164 or local format)
     * @param  string  $message The message body
     * @return array{success: bool, response?: array, error?: string, message_id?: string}
     */
    public function send(string $phone, string $message): array;

    /**
     * Check the provider account balance (if supported).
     *
     * @return array{success: bool, balance?: float, currency?: string, error?: string}
     */
    public function getBalance(): array;

    /**
     * Query delivery status of a previously sent message.
     *
     * @param  string  $messageId The provider's message ID
     * @return array{success: bool, status?: string, error?: string}
     */
    public function getDeliveryStatus(string $messageId): array;

    /**
     * Return the provider's human-readable name.
     */
    public function name(): string;
}
