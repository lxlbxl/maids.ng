<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class NotificationController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $limit = (int)($params['limit'] ?? 20);
        $offset = (int)($params['offset'] ?? 0);

        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total unread
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as unread_count FROM notifications
            WHERE user_id = ? AND read_at IS NULL
        ");
        $stmt->execute([$userId]);
        $unread = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $notifications,
            'meta' => ['unread_count' => (int)$unread['unread_count']]
        ]);
    }

    public function markRead(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $notificationId = (int)($args['id'] ?? 0);

        $stmt = $this->pdo->prepare("
            UPDATE notifications SET read_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $updated = $stmt->execute([$notificationId, $userId]);

        if (!$updated || $stmt->rowCount() === 0) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Notification not found'
            ], 404);
        }

        return $this->jsonResponse($response, ['success' => true]);
    }

    // Admin: get all notifications (system-wide)
    public function adminIndex(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $limit = (int)($params['limit'] ?? 50);
        $offset = (int)($params['offset'] ?? 0);
        $type = $params['type'] ?? null;

        $sql = "SELECT n.*, u.phone, u.user_type FROM notifications n
                JOIN users u ON n.user_id = u.id";
        $params = [];
        if ($type) {
            $sql .= " WHERE n.type = ?";
            $params[] = $type;
        }
        $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $data
        ]);
    }

    // Helper to send notifications (can be called from other services)
    public static function send(PDO $pdo, int $userId, string $type, string $channel, string $title, string $message, array $payload = []): bool
    {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, channel, title, message, payload, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $result = $stmt->execute([
            $userId,
            $type,
            $channel,
            $title,
            $message,
            json_encode($payload)
        ]);

        // TODO: Dispatch to actual SMS/WhatsApp/Email provider here
        // For now, just store and mark as sent immediately (placeholder)
        if ($result) {
            $notifId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("UPDATE notifications SET status='sent', sent_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$notifId]);
        }

        return $result;
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
