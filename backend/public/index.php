<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use App\Handlers\ErrorHandler;
use Psr\Log\LoggerInterface;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Build Container
$containerBuilder = new ContainerBuilder();

// Add container definitions
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');

$container = $containerBuilder->build();

// Create App with Container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Determine if debug mode is enabled
$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

// Add Error Middleware with custom error handler
$errorMiddleware = $app->addErrorMiddleware($debug, true, true);

// Set custom error handler
$logger = $container->get(LoggerInterface::class);
$errorHandler = new ErrorHandler(
    $app->getResponseFactory(),
    $logger,
    $debug
);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Add CORS Middleware
$app->add(new App\Middleware\CorsMiddleware());

// Add Security Headers Middleware (X-Frame-Options, CSP, HSTS, etc.)
$app->add(new App\Middleware\SecurityHeadersMiddleware());

// Add Response Format Middleware (standardize API responses)
$app->add(new App\Middleware\ResponseFormatMiddleware());

// Add Rate Limiting Middleware (60 requests per minute default, stricter for auth endpoints)
$app->add(new App\Middleware\RateLimitMiddleware(60, 60));

// Add Session Middleware
$app->add(new App\Middleware\SessionMiddleware());

// Register routes
$routes = require __DIR__ . '/../config/routes.php';
$routes($app);

// Register admin routes
$adminRoutes = require __DIR__ . '/../config/admin-routes.php';
$adminRoutes($app);

$app->run();
