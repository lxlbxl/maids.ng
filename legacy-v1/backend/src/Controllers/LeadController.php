<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\NotificationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LeadController
{
    private PDO $pdo;
    private NotificationService $notificationService;

    public function __construct(PDO $pdo, NotificationService $notificationService)
    {
        $this->pdo = $pdo;
        $this->notificationService = $notificationService;
    }

    public function capture(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $phone = $data['phone'] ?? $data['whatsapp'] ?? '';

        if (empty($phone)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone number is required'
            ], 400);
        }

        $phone = $this->normalizePhone($phone);

        // Check if lead already exists
        $stmt = $this->pdo->prepare("SELECT id FROM leads WHERE phone = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$phone]);
        $existing = $stmt->fetch();

        // Create lead record
        $stmt = $this->pdo->prepare("
            INSERT INTO leads (phone, source, step, flow_type, user_agent, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $phone,
            $data['source'] ?? 'website',
            $data['step'] ?? null,
            $data['flow_type'] ?? $data['flowType'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        $leadId = (int)$this->pdo->lastInsertId();

        // Trigger notification webhook
        $this->notificationService->notifyNewLead([
            'id' => $leadId,
            'phone' => $phone,
            'source' => $data['source'] ?? 'website',
            'step' => $data['step'] ?? null,
            'flow_type' => $data['flow_type'] ?? null
        ]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Lead captured',
            'lead_id' => $leadId,
            'is_returning' => (bool)$existing
        ], 201);
    }

    public function convert(Request $request, Response $response, array $args): Response
    {
        $leadId = (int)$args['id'];

        $stmt = $this->pdo->prepare("
            UPDATE leads SET
                converted = 1,
                converted_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$leadId]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Lead marked as converted'
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+234')) {
            $phone = '0' . substr($phone, 4);
        } elseif (str_starts_with($phone, '234')) {
            $phone = '0' . substr($phone, 3);
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
