<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\MatchingService;
use App\Services\NotificationService;
use App\Services\OtpService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ClientRequestController
{
    private PDO $pdo;
    private AuthService $authService;
    private MatchingService $matchingService;
    private NotificationService $notificationService;
    private OtpService $otpService;

    public function __construct(
        PDO $pdo,
        AuthService $authService,
        MatchingService $matchingService,
        NotificationService $notificationService,
        OtpService $otpService
    ) {
        $this->pdo = $pdo;
        $this->authService = $authService;
        $this->matchingService = $matchingService;
        $this->notificationService = $notificationService;
        $this->otpService = $otpService;
    }

    /**
     * POST /api/client-requests
     *
     * Unified client maid request endpoint:
     *  - Searches for matching maids
     *  - If match found: logs service_request as 'matched' and returns maids
     *  - If no match: registers/identifies client, creates employer profile,
     *    logs service_request as 'pending', notifies admin, sends OTP to new users
     */
    public function submit(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        // --- Validation ---
        $phone = trim($data['phone'] ?? $data['whatsapp'] ?? '');
        $helpType = trim($data['help_type'] ?? $data['helpType'] ?? '');

        if (empty($phone)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone number is required'
            ], 400);
        }

        if (empty($helpType)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Help type is required'
            ], 400);
        }

        $phone = $this->normalizePhone($phone);

        // --- Build search criteria ---
        $criteria = [
            'help_type' => $helpType,
            'accommodation' => $data['accommodation'] ?? $data['accommodation_preference'] ?? null,
            'location' => $data['location'] ?? null,
            'budget_min' => $data['budget_min'] ?? $data['budgetMin'] ?? null,
            'budget_max' => $data['budget_max'] ?? $data['budgetMax'] ?? null,
            'start_date' => $data['start_date'] ?? $data['startDate'] ?? null,
        ];

        $limit = (int) ($data['limit'] ?? 3);
        $matches = $this->matchingService->findMatches($criteria, $limit);

        // --- Common request data for DB insert ---
        $requestData = [
            'phone' => $phone,
            'full_name' => trim($data['full_name'] ?? $data['name'] ?? ''),
            'help_type' => $helpType,
            'location' => $criteria['location'],
            'accommodation_preference' => $criteria['accommodation'],
            'budget_min' => $criteria['budget_min'] ? (int) $criteria['budget_min'] : null,
            'budget_max' => $criteria['budget_max'] ? (int) $criteria['budget_max'] : null,
            'start_date' => $criteria['start_date'],
            'additional_notes' => trim($data['notes'] ?? $data['additional_notes'] ?? ''),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        // =====================================================================
        // BRANCH A: Matches found → log as 'matched' and return maids
        // =====================================================================
        if (!empty($matches)) {
            $helperIds = array_column($matches, 'id');

            $serviceRequestId = $this->insertServiceRequest(
                $requestData,
                'matched',
                null, // user_id not required for matched flow
                $helperIds
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'status' => 'matched',
                'message' => 'We found matching maids for you!',
                'maids' => $matches,
                'count' => count($matches),
                'service_request_id' => $serviceRequestId,
            ]);
        }

        // =====================================================================
        // BRANCH B: No matches → register/identify client and log pending request
        // =====================================================================

        // Check if user already exists
        $user = $this->authService->getUserByPhone($phone);
        $isNew = false;
        $otpSent = false;

        if (!$user) {
            // Auto-register with a temporary random PIN (client will set their own via OTP)
            $tempPin = (string) random_int(100000, 999999);
            $user = $this->authService->register($phone, $tempPin, 'employer');

            if (!$user) {
                // Edge case: race condition – try fetching again
                $user = $this->authService->getUserByPhone($phone);
            }

            $isNew = true;
        }

        $userId = $user['id'] ?? null;

        // Ensure employer profile exists
        if ($userId) {
            $this->ensureEmployerProfile($userId, $requestData);
        }

        // Log service request as 'pending'
        $serviceRequestId = $this->insertServiceRequest($requestData, 'pending', $userId, []);

        // Notify admin
        $this->notificationService->notifyPendingRequest([
            'id' => $serviceRequestId,
            'phone' => $phone,
            'full_name' => $requestData['full_name'],
            'help_type' => $helpType,
            'location' => $requestData['location'],
            'is_new_user' => $isNew,
        ]);

        // Send OTP to new users so they can activate/set their PIN
        if ($isNew && $userId) {
            try {
                $this->otpService->sendToPhone($phone, 'pin_setup');
                $otpSent = true;
            } catch (\Throwable $e) {
                // OTP failure is non-fatal – request is still logged
            }
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'status' => 'pending',
            'message' => 'No exact match found right now. We\'ve logged your request and our team will reach out shortly.',
            'service_request_id' => $serviceRequestId,
            'otp_sent' => $otpSent,
            'is_new_user' => $isNew,
        ], 201);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function insertServiceRequest(
        array $data,
        string $status,
        ?int $userId,
        array $matchedHelperIds
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO service_requests (
                user_id, phone, full_name, help_type, location,
                accommodation_preference, budget_min, budget_max,
                start_date, additional_notes, status, matched_helper_ids, ip_address
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $userId,
            $data['phone'],
            $data['full_name'] ?: null,
            $data['help_type'],
            $data['location'],
            $data['accommodation_preference'],
            $data['budget_min'],
            $data['budget_max'],
            $data['start_date'],
            $data['additional_notes'] ?: null,
            $status,
            empty($matchedHelperIds) ? null : json_encode($matchedHelperIds),
            $data['ip_address'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function ensureEmployerProfile(int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM employers WHERE user_id = ?");
        $stmt->execute([$userId]);

        if (!$stmt->fetch()) {
            $stmt = $this->pdo->prepare("
                INSERT INTO employers (
                    user_id, full_name, location, help_type,
                    accommodation_preference, budget_min, budget_max, start_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $data['full_name'] ?: null,
                $data['location'],
                $data['help_type'],
                $data['accommodation_preference'],
                $data['budget_min'],
                $data['budget_max'],
                $data['start_date'],
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+234')) {
            return '0' . substr($phone, 4);
        } elseif (str_starts_with($phone, '234')) {
            return '0' . substr($phone, 3);
        }

        return $phone;
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
