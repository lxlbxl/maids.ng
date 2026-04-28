<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\NotificationService;

class MessageController
{
    private PDO $pdo;
    private NotificationService $notificationService;

    public function __construct(PDO $pdo, NotificationService $notificationService)
    {
        $this->pdo = $pdo;
        $this->notificationService = $notificationService;
    }

    public function sendMessage(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $senderId = $request->getAttribute('user_id');

        $bookingId = (int) ($data['booking_id'] ?? 0);
        $messageText = $data['message'] ?? '';

        if (!$bookingId || !$messageText) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Booking ID and message are required'], 400);
        }

        // Verify booking and determine receiver
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

        // Determine receiver
        if ($senderId == $booking['employer_user_id']) {
            $receiverId = $booking['helper_user_id'];
        } elseif ($senderId == $booking['helper_user_id']) {
            $receiverId = $booking['employer_user_id'];
        } else {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO messages (booking_id, sender_id, receiver_id, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$bookingId, $senderId, $receiverId, $messageText]);

        $messageId = (int) $this->pdo->lastInsertId();

        // Notify Receiver (TODO: Add method to NotificationService, maybe push notification or email digest)
        // $this->notificationService->notifyNewMessage($receiverId, $messageId);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Message sent',
            'message_id' => $messageId
        ], 201);
    }

    public function getMessages(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int) $args['id'];
        $userId = $request->getAttribute('user_id');

        // Verify authorization
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
            SELECT m.*, u.user_type as sender_type
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.booking_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$bookingId]);
        $messages = $stmt->fetchAll();

        return $this->jsonResponse($response, ['success' => true, 'messages' => $messages]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
