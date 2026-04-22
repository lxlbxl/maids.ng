<?php

declare(strict_types=1);

return [
    'default' => 'flutterwave',

    'flutterwave' => [
        'public_key' => $_ENV['FLUTTERWAVE_PUBLIC_KEY'] ?? '',
        'secret_key' => $_ENV['FLUTTERWAVE_SECRET_KEY'] ?? '',
        'encryption_key' => $_ENV['FLUTTERWAVE_ENCRYPTION_KEY'] ?? '',
        'base_url' => 'https://api.flutterwave.com/v3',
    ],

    'paystack' => [
        'public_key' => $_ENV['PAYSTACK_PUBLIC_KEY'] ?? '',
        'secret_key' => $_ENV['PAYSTACK_SECRET_KEY'] ?? '',
        'base_url' => 'https://api.paystack.co',
    ],

    'service_fee' => [
        'amount' => (int)($_ENV['SERVICE_FEE_AMOUNT'] ?? 10000),
        'currency' => $_ENV['SERVICE_FEE_CURRENCY'] ?? 'NGN',
    ],
];
