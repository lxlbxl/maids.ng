<?php

declare(strict_types=1);

return [
    'n8n' => [
        'base_url' => $_ENV['N8N_BASE_URL'] ?? 'https://n8n.ai20.city/webhook',
        'secret' => $_ENV['N8N_WEBHOOK_SECRET'] ?? '',
        'timeout' => 30,
        'retry_attempts' => 3,
    ],

    'events' => [
        'booking_created' => '/booking-created',
        'payment_success' => '/payment-success',
        'payment_failed' => '/payment-failed',
        'helper_verified' => '/helper-verified',
        'verification_rejected' => '/verification-rejected',
        'new_lead' => '/new-lead',
        'new_rating' => '/new-rating',
        'nin_verify' => '/nin-verify',
    ],

    'qoreid' => [
        'base_url' => 'https://api.qoreid.com/v1',
        'token' => $_ENV['QOREID_TOKEN'] ?? '',
        'nin_endpoint' => '/ng/identities/nin',
    ],
];
