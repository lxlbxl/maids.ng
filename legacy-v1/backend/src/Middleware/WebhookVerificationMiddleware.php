<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class WebhookVerificationMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private string $flutterwaveSecretHash;
    private string $paystackSecretKey;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->flutterwaveSecretHash = $_ENV['FLUTTERWAVE_SECRET_HASH'] ?? $_ENV['FLUTTERWAVE_SECRET_KEY'] ?? '';
        $this->paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Determine which gateway this webhook is for
        if (str_contains($path, 'flutterwave') || $this->isFlutterwaveWebhook($request)) {
            if (!$this->verifyFlutterwaveSignature($request)) {
                return $this->unauthorizedResponse('Invalid Flutterwave webhook signature');
            }
        } elseif (str_contains($path, 'paystack') || $this->isPaystackWebhook($request)) {
            if (!$this->verifyPaystackSignature($request)) {
                return $this->unauthorizedResponse('Invalid Paystack webhook signature');
            }
        }

        // Mark request as verified
        $request = $request->withAttribute('webhook_verified', true);

        return $handler->handle($request);
    }

    /**
     * Verify Flutterwave webhook signature
     * Flutterwave sends a hash in the 'verif-hash' header
     */
    private function verifyFlutterwaveSignature(ServerRequestInterface $request): bool
    {
        // Get the signature from header
        $signature = $request->getHeaderLine('verif-hash');

        if (empty($signature)) {
            $this->logger->warning('Flutterwave webhook missing signature');
            return false;
        }

        // Compare with our secret hash
        if (empty($this->flutterwaveSecretHash)) {
            $this->logger->warning('Flutterwave secret hash not configured');
            return true; // Allow in development if not configured
        }

        $isValid = hash_equals($this->flutterwaveSecretHash, $signature);

        if (!$isValid) {
            $this->logger->warning('Flutterwave webhook signature mismatch', [
                'received' => substr($signature, 0, 10) . '...',
            ]);
        }

        return $isValid;
    }

    /**
     * Verify Paystack webhook signature
     * Paystack uses HMAC SHA512 with the secret key
     */
    private function verifyPaystackSignature(ServerRequestInterface $request): bool
    {
        // Get the signature from header
        $signature = $request->getHeaderLine('x-paystack-signature');

        if (empty($signature)) {
            $this->logger->warning('Paystack webhook missing signature');
            return false;
        }

        if (empty($this->paystackSecretKey)) {
            $this->logger->warning('Paystack secret key not configured');
            return true; // Allow in development if not configured
        }

        // Get raw body
        $body = (string) $request->getBody();

        // Rewind body stream for later use
        $request->getBody()->rewind();

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha512', $body, $this->paystackSecretKey);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            $this->logger->warning('Paystack webhook signature mismatch', [
                'received' => substr($signature, 0, 20) . '...',
            ]);
        }

        return $isValid;
    }

    /**
     * Check if request is from Flutterwave based on headers/body
     */
    private function isFlutterwaveWebhook(ServerRequestInterface $request): bool
    {
        // Flutterwave sends 'verif-hash' header
        if ($request->hasHeader('verif-hash')) {
            return true;
        }

        // Check body for Flutterwave event structure
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['event']) && str_starts_with($body['event'] ?? '', 'charge.')) {
            return true;
        }

        return false;
    }

    /**
     * Check if request is from Paystack based on headers/body
     */
    private function isPaystackWebhook(ServerRequestInterface $request): bool
    {
        // Paystack sends 'x-paystack-signature' header
        if ($request->hasHeader('x-paystack-signature')) {
            return true;
        }

        // Check body for Paystack event structure
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['event']) && isset($body['data']['reference'])) {
            return true;
        }

        return false;
    }

    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $message,
        ]));

        $this->logger->error('Webhook verification failed', ['message' => $message]);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
