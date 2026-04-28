<?php

declare(strict_types=1);

namespace App\Handlers;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Throwable;

class ErrorHandler
{
    private ResponseFactoryInterface $responseFactory;
    private LoggerInterface $logger;
    private bool $debug;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        LoggerInterface $logger,
        bool $debug = false
    ) {
        $this->responseFactory = $responseFactory;
        $this->logger = $logger;
        $this->debug = $debug;
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        // Log the error
        $this->logError($request, $exception);

        // Create response
        $response = $this->responseFactory->createResponse();

        // Determine status code
        $statusCode = $this->getStatusCode($exception);

        // Build error response
        $error = $this->buildErrorResponse($exception, $statusCode);

        // Write JSON response
        $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    private function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getCode();
        }

        // Map common exceptions to status codes
        $exceptionClass = get_class($exception);

        $statusCodeMap = [
            \InvalidArgumentException::class => 400,
            \DomainException::class => 400,
            \RuntimeException::class => 500,
            \PDOException::class => 500,
            \TypeError::class => 500,
        ];

        foreach ($statusCodeMap as $class => $code) {
            if ($exception instanceof $class) {
                return $code;
            }
        }

        return 500;
    }

    private function buildErrorResponse(Throwable $exception, int $statusCode): array
    {
        $error = [
            'success' => false,
            'error' => $this->getErrorMessage($exception, $statusCode),
            'code' => $this->getErrorCode($exception, $statusCode),
        ];

        // Add debug information in development
        if ($this->debug) {
            $error['debug'] = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->formatTrace($exception->getTrace()),
            ];
        }

        return $error;
    }

    private function getErrorMessage(Throwable $exception, int $statusCode): string
    {
        // Use exception message for HTTP exceptions
        if ($exception instanceof HttpException) {
            return $exception->getMessage();
        }

        // User-friendly messages for production
        if (!$this->debug) {
            $messages = [
                400 => 'Invalid request. Please check your input.',
                401 => 'Authentication required. Please login.',
                403 => 'You do not have permission to access this resource.',
                404 => 'The requested resource was not found.',
                405 => 'This method is not allowed for this endpoint.',
                409 => 'The request conflicts with the current state.',
                422 => 'The request could not be processed.',
                429 => 'Too many requests. Please try again later.',
                500 => 'An unexpected error occurred. Please try again.',
                502 => 'Service temporarily unavailable.',
                503 => 'Service is under maintenance.',
            ];

            return $messages[$statusCode] ?? 'An error occurred.';
        }

        return $exception->getMessage();
    }

    private function getErrorCode(Throwable $exception, int $statusCode): string
    {
        if ($exception instanceof HttpNotFoundException) {
            return 'NOT_FOUND';
        }

        if ($exception instanceof HttpMethodNotAllowedException) {
            return 'METHOD_NOT_ALLOWED';
        }

        $codes = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_ERROR',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE',
        ];

        return $codes[$statusCode] ?? 'ERROR';
    }

    private function formatTrace(array $trace): array
    {
        $formatted = [];

        foreach (array_slice($trace, 0, 10) as $frame) {
            $formatted[] = sprintf(
                '%s%s%s() at %s:%d',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? 'unknown',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0
            );
        }

        return $formatted;
    }

    private function logError(ServerRequestInterface $request, Throwable $exception): void
    {
        $context = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        // Add request body for non-GET requests (sanitized)
        if ($request->getMethod() !== 'GET') {
            $body = $request->getParsedBody();
            if (is_array($body)) {
                // Remove sensitive fields
                $sensitiveFields = ['pin', 'password', 'pin_hash', 'password_hash', 'token', 'api_key', 'secret'];
                foreach ($sensitiveFields as $field) {
                    if (isset($body[$field])) {
                        $body[$field] = '[REDACTED]';
                    }
                }
                $context['body'] = $body;
            }
        }

        // Log based on severity
        $statusCode = $this->getStatusCode($exception);

        if ($statusCode >= 500) {
            $this->logger->error('Server error', $context);
        } elseif ($statusCode >= 400) {
            $this->logger->warning('Client error', $context);
        } else {
            $this->logger->info('Request error', $context);
        }
    }
}
