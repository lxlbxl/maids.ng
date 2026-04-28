<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\NotificationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PaymentController
{
    private PDO $pdo;
    private PaymentService $paymentService;
    private NotificationService $notificationService;

    public function __construct(
        PDO $pdo,
        PaymentService $paymentService,
        NotificationService $notificationService
    ) {
        $this->pdo = $pdo;
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
    }

    public function getConfig(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $gateway = $params['gateway'] ?? 'flutterwave';

        $config = $this->paymentService->getPaymentConfig($gateway);

        // Format for existing frontend compatibility
        return $this->jsonResponse($response, [
            'public-key' => $config['public_key'],
            'amount' => $config['amount'],
            'currency' => $config['currency']
        ]);
    }

    public function initialize(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $bookingId = isset($data['booking_id']) ? (int)$data['booking_id'] : null;
        $serviceRequestId = isset($data['service_request_id']) ? (int)$data['service_request_id'] : null;

        if (!$bookingId && !$serviceRequestId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Booking ID or Service Request ID is required'
            ], 400);
        }

        $gateway = $data['gateway'] ?? 'flutterwave';
        $type = $data['type'] ?? 'service_fee';
        
        $paymentData = $this->paymentService->createPayment($bookingId, $gateway, $type, $serviceRequestId);

        return $this->jsonResponse($response, [
            'success' => true,
            'payment' => $paymentData
        ]);
    }

    public function verifyCallback(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $params = $request->getQueryParams();

        $txRef = $data['tx_ref'] ?? $params['tx_ref'] ?? null;
        $transactionId = $data['transaction_id'] ?? $params['transaction_id'] ?? null;
        $gateway = $data['gateway'] ?? 'flutterwave';

        if (!$txRef) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Transaction reference required'
            ], 400);
        }

        $result = match ($gateway) {
            'paystack' => $this->paymentService->verifyPaystackPayment($txRef),
            default => $this->paymentService->verifyFlutterwavePayment($txRef, $transactionId ?? '')
        };

        if ($result['success']) {
            // Get booking and user details for notification
            $payment = $this->paymentService->getPayment($txRef);
            if ($payment) {
                $this->sendSuccessNotification($payment);
            }
        }

        return $this->jsonResponse($response, $result);
    }

    public function success(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $txRef = $data['tx_ref'] ?? null;
        $transactionId = $data['transaction_id'] ?? null;

        if (!$txRef) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Transaction reference required'
            ], 400);
        }

        // Mark payment as success
        $this->paymentService->markPaymentSuccess($txRef, $transactionId ?? '', $data);

        // Get payment details
        $payment = $this->paymentService->getPayment($txRef);

        if ($payment) {
            $this->sendSuccessNotification($payment);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Payment recorded successfully',
            'payment' => $payment
        ]);
    }

    public function failure(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $txRef = $data['tx_ref'] ?? null;
        $reason = $data['reason'] ?? $data['error_message'] ?? 'Payment failed';

        if (!$txRef) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Transaction reference required'
            ], 400);
        }

        $this->paymentService->markPaymentFailed($txRef, $reason);

        // Get payment details for notification
        $payment = $this->paymentService->getPayment($txRef);
        if ($payment) {
            $this->sendFailureNotification($payment);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Payment failure recorded'
        ]);
    }

    private function sendSuccessNotification(array $payment): void
    {
        // Get helper details
        $stmt = $this->pdo->prepare("
            SELECT h.*, u.phone FROM helpers h
            JOIN users u ON h.user_id = u.id
            WHERE h.id = ?
        ");
        $stmt->execute([$payment['helper_id']]);
        $helper = $stmt->fetch();

        // Get employer details
        $stmt = $this->pdo->prepare("
            SELECT e.*, u.phone FROM employers e
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$payment['employer_id']]);
        $employer = $stmt->fetch();

        // Get booking details
        $stmt = $this->pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$payment['booking_id']]);
        $booking = $stmt->fetch();

        if ($helper && $employer && $booking) {
            $this->notificationService->notifyPaymentSuccess($payment, $booking, $helper, $employer);
        }
    }

    private function sendFailureNotification(array $payment): void
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, u.phone FROM employers e
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$payment['employer_id']]);
        $employer = $stmt->fetch();

        $stmt = $this->pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$payment['booking_id']]);
        $booking = $stmt->fetch();

        if ($employer && $booking) {
            $this->notificationService->notifyPaymentFailed($payment, $booking, $employer);
        }
    }

    public function getBanks(Request $request, Response $response): Response
    {
        $banks = $this->paymentService->getBanks();
        return $this->jsonResponse($response, ['success' => true, 'banks' => $banks]);
    }

    public function resolveAccount(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $accountNumber = $data['account_number'] ?? '';
        $bankCode = $data['bank_code'] ?? '';

        if (!$accountNumber || !$bankCode) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Account number and bank code required'], 400);
        }

        $result = $this->paymentService->resolveAccount($accountNumber, $bankCode);

        if (isset($result['error'])) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $result['error']], 400);
        }

        return $this->jsonResponse($response, ['success' => true, 'account' => $result]);
    }

    /**
     * Handle Flutterwave webhook
     */
    public function flutterwaveWebhook(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Extract event type and data
        $event = $data['event'] ?? '';
        $eventData = $data['data'] ?? [];

        // Only process charge.completed events
        if ($event !== 'charge.completed') {
            return $this->jsonResponse($response, ['status' => 'ignored', 'event' => $event]);
        }

        $txRef = $eventData['tx_ref'] ?? null;
        $status = $eventData['status'] ?? '';

        if (!$txRef) {
            return $this->jsonResponse($response, ['error' => 'Missing transaction reference'], 400);
        }

        if ($status === 'successful') {
            $transactionId = (string) ($eventData['id'] ?? '');
            $this->paymentService->markPaymentSuccess($txRef, $transactionId, $eventData);

            $payment = $this->paymentService->getPayment($txRef);
            if ($payment) {
                $this->sendSuccessNotification($payment);
            }
        } else {
            $this->paymentService->markPaymentFailed($txRef, 'Payment not successful: ' . $status);
        }

        return $this->jsonResponse($response, ['status' => 'processed']);
    }

    /**
     * Handle Paystack webhook
     */
    public function paystackWebhook(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $event = $data['event'] ?? '';
        $eventData = $data['data'] ?? [];

        // Only process charge.success events
        if ($event !== 'charge.success') {
            return $this->jsonResponse($response, ['status' => 'ignored', 'event' => $event]);
        }

        $reference = $eventData['reference'] ?? null;
        $status = $eventData['status'] ?? '';

        if (!$reference) {
            return $this->jsonResponse($response, ['error' => 'Missing reference'], 400);
        }

        if ($status === 'success') {
            $gatewayRef = (string) ($eventData['id'] ?? '');
            $this->paymentService->markPaymentSuccess($reference, $gatewayRef, $eventData);

            $payment = $this->paymentService->getPayment($reference);
            if ($payment) {
                $this->sendSuccessNotification($payment);
            }
        } else {
            $this->paymentService->markPaymentFailed($reference, 'Payment not successful: ' . $status);
        }

        return $this->jsonResponse($response, ['status' => 'processed']);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
