<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Services\SchedulerService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\EmailService;
use App\Services\SmsService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Get database config
$config = require __DIR__ . '/../config/database.php';

// Setup Logger
$logger = new Logger('scheduler');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../storage/logs/scheduler.log', Logger::INFO));

// Create connection
$connection = new Connection($config);
$pdo = $connection->getPdo();

// Setup Services (Dependency Injection manually since no container here easily)
// Ideally we should use the container from bootstrap.php if available

$emailService = new EmailService($logger);
$smsService = new SmsService($logger);
$notificationService = new NotificationService($emailService, $smsService, $logger);

// Payment config loads from settings usually, but simpler here
$paymentConfig = [
    'flutterwave' => [
        'public_key' => $_ENV['FLW_PUBLIC_KEY'] ?? '',
        'secret_key' => $_ENV['FLW_SECRET_KEY'] ?? '',
        'base_url' => 'https://api.flutterwave.com/v3'
    ],
    'paystack' => [
        'public_key' => $_ENV['PAYSTACK_PUBLIC_KEY'] ?? '',
        'secret_key' => $_ENV['PAYSTACK_SECRET_KEY'] ?? '',
        'base_url' => 'https://api.paystack.co'
    ],
    'service_fee' => ['amount' => 10000, 'currency' => 'NGN']
];

$paymentService = new PaymentService($pdo, $logger, $paymentConfig);

$scheduler = new SchedulerService($pdo, $logger, $notificationService, $paymentService);

echo "Running Scheduler...\n";
$scheduler->run();
echo "Scheduler run complete.\n";
