<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentService;
use App\Services\VerificationService;
use App\Services\NotificationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StandaloneVerificationController
{
    private PDO $pdo;
    private PaymentService $paymentService;
    private VerificationService $verificationService;
    private NotificationService $notificationService;

    public function __construct(
        PDO $pdo,
        PaymentService $paymentService,
        VerificationService $verificationService,
        NotificationService $notificationService
    ) {
        $this->pdo = $pdo;
        $this->paymentService = $paymentService;
        $this->verificationService = $verificationService;
        $this->notificationService = $notificationService;
    }

    public function initiate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $email = $data['email'] ?? '';
        $nin = $data['nin'] ?? '';
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $gateway = $data['gateway'] ?? 'flutterwave';

        if (empty($email) || empty($nin) || empty($firstName) || empty($lastName)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Email, NIN, first name, and last name are required'
            ], 400);
        }

        // Validate NIN format
        if (!preg_match('/^\d{11}$/', $nin)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'NIN must be exactly 11 digits'
            ], 400);
        }

        // Create Payment Intent via PaymentService
        // Type: nin_verification, booking/service_request IDs = null
        try {
            $paymentData = $this->paymentService->createPayment(null, $gateway, 'nin_verification', null);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to initialize payment: ' . $e->getMessage()
            ], 500);
        }

        $txRef = $paymentData['tx_ref'];
        $amount = $paymentData['amount'];

        // Generate unique reference for this verification process
        $reference = 'NIN_V_' . strtoupper(substr(md5((string)time() . $email), 0, 10));

        // Save to standalone_verifications
        $stmt = $this->pdo->prepare("
            INSERT INTO standalone_verifications 
            (reference, email, requested_nin, first_name, last_name, payment_reference, amount_paid, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $reference,
            $email,
            $nin,
            $firstName,
            $lastName,
            $txRef,
            $amount
        ]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Verification initiated, proceed to payment',
            'reference' => $reference,
            'payment' => $paymentData
        ]);
    }

    public function verifyPayment(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $params = $request->getQueryParams();

        // This could be callback from frontend containing reference or gateway tx_ref
        $reference = $data['reference'] ?? $params['reference'] ?? null;
        $transactionId = $data['transaction_id'] ?? $params['transaction_id'] ?? null;

        if (!$reference) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Verification reference is required'
            ], 400);
        }

        // Fetch the verification record
        $stmt = $this->pdo->prepare("SELECT * FROM standalone_verifications WHERE reference = ?");
        $stmt->execute([$reference]);
        $verification = $stmt->fetch();

        if (!$verification) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Verification record not found'
            ], 404);
        }

        // If already completed, just return it
        if ($verification['status'] === 'completed') {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Verification already completed',
                'data' => $verification
            ]);
        }

        $txRef = $verification['payment_reference'];

        // We pull gateway from payments table
        $stmtPay = $this->pdo->prepare("SELECT gateway FROM payments WHERE tx_ref = ?");
        $stmtPay->execute([$txRef]);
        $gateway = $stmtPay->fetchColumn() ?: 'flutterwave';

        // Verify payment via gateway directly
        $result = match ($gateway) {
            'paystack' => $this->paymentService->verifyPaystackPayment($txRef),
            default => $this->paymentService->verifyFlutterwavePayment($txRef, $transactionId ?? '')
        };

        if (!$result['success']) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Payment verification failed: ' . ($result['error'] ?? 'Unknown error')
            ], 400);
        }

        // Payment successful, update standalone verification DB to paid temporarily
        $this->pdo->prepare("UPDATE standalone_verifications SET status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
             ->execute([$verification['id']]);

        // Proceed to NIN matching engine
        $matchResult = $this->verificationService->preMatchNin(
            $verification['requested_nin'],
            $verification['first_name'],
            $verification['last_name']
        );

        $qoreidResponse = isset($matchResult['data']) ? json_encode($matchResult['data']) : null;
        $isMatch = $matchResult['success'] ? 1 : 0;
        
        // Finalize Record
        $updateStmt = $this->pdo->prepare("
            UPDATE standalone_verifications 
            SET status = 'completed',
                match_result = ?,
                qoreid_response = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $isMatch,
            $qoreidResponse,
            $verification['id']
        ]);

        // Refetch updated to return
        $stmt->execute([$reference]);
        $completedVerification = $stmt->fetch();

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => $isMatch ? 'Match Found' : 'Mismatch',
            'data' => [
                'reference' => $completedVerification['reference'],
                'first_name' => $completedVerification['first_name'],
                'last_name' => $completedVerification['last_name'],
                'requested_nin' => '********' . substr($completedVerification['requested_nin'], -3),
                'match_result' => (bool)$completedVerification['match_result'],
                'status' => $completedVerification['status'],
                'qoreid_response' => json_decode($completedVerification['qoreid_response'] ?: '{}')
            ]
        ]);
    }

    public function getReport(Request $request, Response $response, array $args): Response
    {
        $reference = $args['reference'] ?? null;

        if (!$reference) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Reference is required'
            ], 400);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM standalone_verifications WHERE reference = ? AND status = 'completed'");
        $stmt->execute([$reference]);
        $verification = $stmt->fetch();

        if (!$verification) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Report not found or verification not completed'
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'reference' => $verification['reference'],
                'email' => $verification['email'],
                'first_name' => $verification['first_name'],
                'last_name' => $verification['last_name'],
                'requested_nin' => '********' . substr($verification['requested_nin'], -3),
                'match_result' => (bool)$verification['match_result'],
                'qoreid_response' => json_decode($verification['qoreid_response'] ?: '{}'),
                'created_at' => $verification['created_at']
            ]
        ]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
