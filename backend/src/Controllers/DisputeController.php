<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\NotificationService;

class DisputeController
{
    private PDO $pdo;
    private NotificationService $notificationService;

    public function __construct(PDO $pdo, NotificationService $notificationService)
    {
        $this->pdo = $pdo;
        $this->notificationService = $notificationService;
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');

        $bookingId = (int) ($data['booking_id'] ?? 0);
        $reason = $data['reason'] ?? '';
        $description = $data['description'] ?? '';

        if (!$bookingId || !$reason) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Booking ID and reason are required'], 400);
        }

        // Verify user is part of the booking
        $stmt = $this->pdo->prepare("
            SELECT b.*, e.user_id as employer_user_id, h.user_id as helper_user_id 
            FROM bookings b
            JOIN employers e ON b.employer_id = e.id
            JOIN helpers h ON b.helper_id = h.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Booking not found'], 404);
        }

        if ($booking['employer_user_id'] != $userId && $booking['helper_user_id'] != $userId) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO disputes (booking_id, raised_by, reason, description, status)
            VALUES (?, ?, ?, ?, 'open')
        ");
        $stmt->execute([$bookingId, $userId, $reason, $description]);

        $disputeId = (int) $this->pdo->lastInsertId();

        // Notify Admin (TODO: Add method to NotificationService)
        // $this->notificationService->notifyAdminDispute($disputeId);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Dispute raised successfully',
            'dispute_id' => $disputeId
        ], 201);
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        // Get disputes where user is the raiser OR involved in the booking
        $stmt = $this->pdo->prepare("
            SELECT d.*, b.reference as booking_ref 
            FROM disputes d
            JOIN bookings b ON d.booking_id = b.id
            JOIN employers e ON b.employer_id = e.id
            JOIN helpers h ON b.helper_id = h.id
            WHERE d.raised_by = ? OR e.user_id = ? OR h.user_id = ?
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        $disputes = $stmt->fetchAll();

        return $this->jsonResponse($response, ['success' => true, 'disputes' => $disputes]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $disputeId = (int) $args['id'];
        $userId = $request->getAttribute('user_id');

        $stmt = $this->pdo->prepare("
            SELECT d.*, b.reference as booking_ref 
            FROM disputes d
            JOIN bookings b ON d.booking_id = b.id
            JOIN employers e ON b.employer_id = e.id
            JOIN helpers h ON b.helper_id = h.id
            WHERE d.id = ? AND (d.raised_by = ? OR e.user_id = ? OR h.user_id = ?)
        ");
        $stmt->execute([$disputeId, $userId, $userId, $userId]);
        $dispute = $stmt->fetch();

        if (!$dispute) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Dispute not found or unauthorized'], 404);
        }

        return $this->jsonResponse($response, ['success' => true, 'dispute' => $dispute]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
