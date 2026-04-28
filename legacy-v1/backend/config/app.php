<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Maids.ng',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'secret' => $_ENV['APP_SECRET'] ?? '',

    'session' => [
        'name' => $_ENV['SESSION_NAME'] ?? 'maids_session',
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
    ],

    'upload' => [
        'max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880),
        'allowed_types' => explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'jpg,jpeg,png,gif,webp,pdf'),
        'path' => __DIR__ . '/../public/uploads',
    ],

    'service_fee' => [
        'amount' => (int)($_ENV['SERVICE_FEE_AMOUNT'] ?? 10000),
        'currency' => $_ENV['SERVICE_FEE_CURRENCY'] ?? 'NGN',
    ],
];
