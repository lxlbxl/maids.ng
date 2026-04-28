<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EmployerController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getProfile(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $stmt = $this->pdo->prepare("
            SELECT e.*, u.phone, u.status as user_status, u.created_at as member_since
            FROM employers e
            JOIN users u ON e.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Profile not found'], 404);
        }

        return $this->jsonResponse($response, ['success' => true, 'profile' => $profile]);
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        // Check if employer profile exists
        $stmt = $this->pdo->prepare("SELECT id FROM employers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $exists = $stmt->fetch();

        if ($exists) {
            $fields = [];
            $params = [];
            $allowedFields = [
                'full_name',
                'location',
                'location_state',
                'location_lga',
                'help_type',
                'accommodation_preference',
                'budget_min',
                'budget_max',
                'start_date',
                'start_urgency',
                'additional_requirements'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return $this->jsonResponse($response, ['success' => false, 'error' => 'No fields to update'], 400);
            }

            $fields[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $exists['id'];

            $sql = "UPDATE employers SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

        } else {
            // Create new profile
            $stmt = $this->pdo->prepare("
                INSERT INTO employers (
                    user_id, full_name, location, location_state, location_lga,
                    help_type, accommodation_preference, budget_min, budget_max,
                    start_date, start_urgency, additional_requirements
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $data['full_name'] ?? '',
                $data['location'] ?? null,
                $data['location_state'] ?? null,
                $data['location_lga'] ?? null,
                $data['help_type'] ?? null,
                $data['accommodation_preference'] ?? null,
                $data['budget_min'] ?? null,
                $data['budget_max'] ?? null,
                $data['start_date'] ?? null,
                $data['start_urgency'] ?? null,
                $data['additional_requirements'] ?? null
            ]);
        }

        return $this->jsonResponse($response, ['success' => true, 'message' => 'Profile updated']);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
