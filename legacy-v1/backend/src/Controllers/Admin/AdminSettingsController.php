<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminSettingsController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->query("SELECT * FROM settings ORDER BY category, key_name");
        $settings = [];

        while ($row = $stmt->fetch()) {
            $category = $row['category'];
            if (!isset($settings[$category])) {
                $settings[$category] = [];
            }
            $settings[$category][$row['key_name']] = [
                'id' => $row['id'],
                'value' => json_decode($row['value'], true),
                'updated_at' => $row['updated_at']
            ];
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'settings' => $settings
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        foreach ($data as $key => $value) {
            $jsonValue = is_array($value) || is_object($value) ? json_encode($value) : json_encode($value);

            $stmt = $this->pdo->prepare("
                INSERT INTO settings (key_name, value, updated_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(key_name) DO UPDATE SET
                    value = excluded.value,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$key, $jsonValue]);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Settings updated'
        ]);
    }

    public function updateSingle(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $data = $request->getParsedBody();

        $value = $data['value'] ?? null;
        $category = $data['category'] ?? 'general';

        if ($value === null) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Value is required'
            ], 400);
        }

        // Special handling for payment settings - allow storing secrets
        if ($category === 'payments' && is_array($value)) {
            // Validate structural integrity but allow values
            // e.g. ensure required keys exist if needed
        }

        $jsonValue = is_array($value) || is_object($value) ? json_encode($value) : json_encode($value);

        $stmt = $this->pdo->prepare("
            INSERT INTO settings (key_name, value, category, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key_name) DO UPDATE SET
                value = excluded.value,
                category = excluded.category,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$key, $jsonValue, $category]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Setting updated'
        ]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];

        $stmt = $this->pdo->prepare("DELETE FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Setting deleted'
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
