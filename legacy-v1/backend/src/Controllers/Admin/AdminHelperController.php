<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\MatchingService;
use App\Services\VerificationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminHelperController
{
    private PDO $pdo;
    private MatchingService $matchingService;
    private VerificationService $verificationService;

    public function __construct(
        PDO $pdo,
        MatchingService $matchingService,
        VerificationService $verificationService
    ) {
        $this->pdo = $pdo;
        $this->matchingService = $matchingService;
        $this->verificationService = $verificationService;
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $queryParams = [];

        // Search filter
        if (!empty($params['search'])) {
            $where[] = "(h.full_name LIKE ? OR u.phone LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }

        // Status filter
        if (!empty($params['status'])) {
            $where[] = "h.status = ?";
            $queryParams[] = $params['status'];
        }

        // Verification filter
        if (!empty($params['verification'])) {
            $where[] = "h.verification_status = ?";
            $queryParams[] = $params['verification'];
        }

        // Work type filter
        if (!empty($params['work_type'])) {
            $where[] = "h.work_type = ?";
            $queryParams[] = $params['work_type'];
        }

        // Location filter
        if (!empty($params['location'])) {
            $where[] = "h.location LIKE ?";
            $queryParams[] = '%' . $params['location'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM helpers h JOIN users u ON h.user_id = u.id {$whereClause}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($queryParams);
        $total = (int) $stmt->fetch()['total'];

        // Get helpers
        $sql = "
            SELECT h.*, u.phone, u.status as user_status,
                   (SELECT COUNT(*) FROM bookings WHERE helper_id = h.id) as booking_count,
                   (SELECT COUNT(*) FROM ratings WHERE helper_id = h.id) as rating_count
            FROM helpers h
            JOIN users u ON h.user_id = u.id
            {$whereClause}
            ORDER BY h.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($queryParams);
        $helpers = $stmt->fetchAll();

        // Parse JSON fields
        foreach ($helpers as &$helper) {
            $helper['skills'] = json_decode($helper['skills'] ?? '[]', true);
            $helper['languages'] = json_decode($helper['languages'] ?? '[]', true);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $helpers,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];

        $stmt = $this->pdo->prepare("
            SELECT h.*, u.phone, u.email, u.status as user_status, u.created_at as user_created_at
            FROM helpers h
            JOIN users u ON h.user_id = u.id
            WHERE h.id = ?
        ");
        $stmt->execute([$helperId]);
        $helper = $stmt->fetch();

        if (!$helper) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Helper not found'
            ], 404);
        }

        // Parse JSON fields
        $helper['skills'] = json_decode($helper['skills'] ?? '[]', true);
        $helper['languages'] = json_decode($helper['languages'] ?? '[]', true);

        // Mock Wallet Balance (Sum of pending payments)
        // In real system, this would be from a 'wallets' table
        $stmt = $this->pdo->prepare("
             SELECT SUM(amount) as balance 
             FROM payments p 
             JOIN bookings b ON p.booking_id = b.id 
             WHERE b.helper_id = ? AND p.status = 'completed' AND p.payment_type = 'salary'
        ");
        $stmt->execute([$helperId]);
        $helper['wallet_balance'] = $stmt->fetchColumn() ?: 0;

        // Get past employers
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT e.id, e.full_name, e.location, b.start_date, b.status as booking_status
            FROM bookings b
            JOIN employers e ON b.employer_id = e.id
            WHERE b.helper_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$helperId]);
        $helper['employers'] = $stmt->fetchAll();

        // Get bookings
        $stmt = $this->pdo->prepare("
            SELECT b.*, e.full_name as employer_name
            FROM bookings b
            JOIN employers e ON b.employer_id = e.id
            WHERE b.helper_id = ?
            ORDER BY b.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$helperId]);
        $helper['bookings'] = $stmt->fetchAll();

        // Get payments/transactions
        $stmt = $this->pdo->prepare("
            SELECT p.*, b.reference as booking_ref
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            WHERE b.helper_id = ?
            ORDER BY p.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$helperId]);
        $helper['payments'] = $stmt->fetchAll();

        // Get ratings
        $stmt = $this->pdo->prepare("
            SELECT r.*, e.full_name as employer_name
            FROM ratings r
            JOIN employers e ON r.employer_id = e.id
            WHERE r.helper_id = ?
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$helperId]);
        $helper['ratings'] = $stmt->fetchAll();

        // Get verifications
        $stmt = $this->pdo->prepare("
            SELECT * FROM verifications
            WHERE helper_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$helperId]);
        $helper['verifications'] = $stmt->fetchAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'helper' => $helper
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();

        $result = $this->matchingService->updateHelper($helperId, $data);

        if (!$result) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Update failed'
            ], 400);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Helper updated'
        ]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];

        // Soft delete - set status to inactive
        $stmt = $this->pdo->prepare("UPDATE helpers SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$helperId]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Helper deactivated'
        ]);
    }

    public function verify(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $adminId = $request->getAttribute('admin_id');
        $data = $request->getParsedBody();

        // Get pending verification
        $stmt = $this->pdo->prepare("
            SELECT id FROM verifications
            WHERE helper_id = ? AND status IN ('pending', 'pending_review')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$helperId]);
        $verification = $stmt->fetch();

        if (!$verification) {
            // Create new verification record and approve directly
            $stmt = $this->pdo->prepare("
                INSERT INTO verifications (helper_id, document_type, status, verified_by, verified_at)
                VALUES (?, 'manual', 'verified', ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$helperId, $adminId]);

            // Update helper
            $this->matchingService->updateHelper($helperId, ['verification_status' => 'verified']);
        } else {
            // Approve existing verification
            $action = $data['action'] ?? 'approve';

            if ($action === 'approve') {
                $this->verificationService->approveVerification($verification['id'], $adminId);
            } else {
                $reason = $data['reason'] ?? 'Rejected by admin';
                $this->verificationService->rejectVerification($verification['id'], $adminId, $reason);
            }
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Verification updated'
        ]);
    }

    public function verifyPhysical(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();
        $adminId = $request->getAttribute('admin_id');

        $status = $data['status'] ?? 'pending';
        $notes = $data['notes'] ?? null;

        if (!in_array($status, ['pending', 'verified', 'failed'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Invalid physical verification status'
            ], 400);
        }

        $updateData = [
            'physical_verification_status' => $status
        ];

        if ($notes !== null) {
            $updateData['field_officer_notes'] = $notes;
        }

        $result = $this->matchingService->updateHelper($helperId, $updateData);

        if (!$result) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to update physical verification status'
            ], 400);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Physical verification status updated'
        ]);
    }

    public function updateBadge(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();

        $badge = $data['badge_level'] ?? 'bronze';
        if (!in_array($badge, ['bronze', 'silver', 'gold'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Invalid badge level'
            ], 400);
        }

        $this->matchingService->updateHelper($helperId, ['badge_level' => $badge]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Badge updated'
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
