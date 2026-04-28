<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminServiceRequestController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * GET /api/admin/service-requests
     * List all service requests, optionally filtered by status.
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $bind = [];

        if ($status) {
            $where[] = 'sr.status = ?';
            $bind[] = $status;
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total count
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM service_requests sr {$whereClause}"
        );
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        // Paginated rows
        $stmt = $this->pdo->prepare("
            SELECT
                sr.id,
                sr.phone,
                sr.full_name,
                sr.help_type,
                sr.location,
                sr.accommodation_preference,
                sr.budget_min,
                sr.budget_max,
                sr.start_date,
                sr.additional_notes,
                sr.status,
                sr.matched_helper_ids,
                sr.admin_notes,
                sr.ip_address,
                sr.created_at,
                sr.updated_at,
                u.id        AS user_id,
                u.user_type AS user_type,
                u.status    AS user_status
            FROM service_requests sr
            LEFT JOIN users u ON sr.user_id = u.id
            {$whereClause}
            ORDER BY sr.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $bind[] = $limit;
        $bind[] = $offset;
        $stmt->execute($bind);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode matched helper IDs JSON
        foreach ($requests as &$req) {
            $req['matched_helper_ids'] = $req['matched_helper_ids']
                ? json_decode($req['matched_helper_ids'], true)
                : [];
        }
        unset($req);

        return $this->jsonResponse($response, [
            'success' => true,
            'requests' => $requests,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * PUT /api/admin/service-requests/{id}
     * Update status and/or admin_notes on a service request.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $data = $request->getParsedBody() ?? [];

        if (!$id) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Invalid ID'], 400);
        }

        $allowed = ['pending', 'matched', 'converted', 'closed'];
        $fields = [];
        $params = [];

        if (isset($data['status'])) {
            if (!in_array($data['status'], $allowed)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'Invalid status. Allowed: ' . implode(', ', $allowed)
                ], 400);
            }
            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }

        if (isset($data['admin_notes'])) {
            $fields[] = 'admin_notes = ?';
            $params[] = $data['admin_notes'];
        }

        if (empty($fields)) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Nothing to update'], 400);
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE service_requests SET ' . implode(', ', $fields) . ' WHERE id = ?'
        );
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Request not found'], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Service request updated',
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
