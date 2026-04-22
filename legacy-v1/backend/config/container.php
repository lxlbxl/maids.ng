<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\EmailService;
use App\Services\SmsService;
use App\Services\OtpService;
use App\Services\AgencyService;
use App\Services\MatchingService;
use App\Services\PaymentService;
use App\Services\VerificationService;
use App\Services\NotificationService;
use App\Services\FileUploadService;
use App\Services\WebhookService;
use App\Middleware\JwtAuthMiddleware;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
        // Logger
    LoggerInterface::class => function (ContainerInterface $c) {
        $logger = new Logger('app');
        $logPath = __DIR__ . '/../storage/logs/app.log';
        $logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
        return $logger;
    },

        // Database Connection
    Connection::class => function (ContainerInterface $c) {
        $config = require __DIR__ . '/database.php';
        return new Connection($config);
    },

    PDO::class => function (ContainerInterface $c) {
        return $c->get(Connection::class)->getPdo();
    },

        // Services
    AuthService::class => function (ContainerInterface $c) {
        return new AuthService(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    MatchingService::class => function (ContainerInterface $c) {
        return new MatchingService(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    AgencyService::class => function (ContainerInterface $c) {
        return new AgencyService(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class)
        );
    },

    PaymentService::class => function (ContainerInterface $c) {
        $config = require __DIR__ . '/payments.php';
        return new PaymentService(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class),
            $config
        );
    },

    VerificationService::class => function (ContainerInterface $c) {
        $config = require __DIR__ . '/webhooks.php';
        return new VerificationService(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class),
            $c->get(NotificationService::class),
            $config['qoreid']
        );
    },

    NotificationService::class => function (ContainerInterface $c) {
        return new NotificationService(
            $c->get(EmailService::class),
            $c->get(SmsService::class),
            $c->get(LoggerInterface::class)
        );
    },

    FileUploadService::class => function (ContainerInterface $c) {
        $config = require __DIR__ . '/app.php';
        return new FileUploadService(
            $c->get(LoggerInterface::class),
            $config['upload']
        );
    },

    WebhookService::class => function (ContainerInterface $c) {
        $config = require __DIR__ . '/webhooks.php';
        return new WebhookService(
            $c->get(LoggerInterface::class),
            $config['n8n']
        );
    },

        // JWT Service
    JwtService::class => function (ContainerInterface $c) {
        return new JwtService();
    },

        // Email Service (SendGrid)
    EmailService::class => function (ContainerInterface $c) {
        return new EmailService(
            $c->get(LoggerInterface::class)
        );
    },

        // SMS Service (Termii)
    SmsService::class => function (ContainerInterface $c) {
        return new SmsService(
            $c->get(LoggerInterface::class)
        );
    },

        // OTP Service
    OtpService::class => function (ContainerInterface $c) {
        return new OtpService(
            $c->get(PDO::class),
            $c->get(LoggerInterface::class),
            $c->get(SmsService::class),
            $c->get(EmailService::class)
        );
    },

        // JWT Auth Middleware
    JwtAuthMiddleware::class => function (ContainerInterface $c) {
        return new JwtAuthMiddleware(
            $c->get(JwtService::class)
        );
    },
];
