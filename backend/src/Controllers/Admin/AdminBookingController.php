<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookingController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(100, max(1, (int)($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $queryParams = [];

        // Status filter
        if (!empty($params['status'])) {
            $where[] = "b.status = ?";
            $queryParams[] = $params['status'];
        }

        // Date range filter
        if (!empty($params['from_date'])) {
            $where[] = "b.created_at >= ?";
            $queryParams[] = $params['from_date'];
        }
        if (!empty($params['to_date'])) {
            $where[] = "b.created_at <= ?";
            $queryParams[] = $params['to_date'] . ' 23:59:59';
        }

        // Search
        if (!empty($params['search'])) {
            $where[] = "(b.reference LIKE ? OR h.full_name LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = "
            SELECT COUNT(*) as total
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            {$whereClause}
        ";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($queryParams);
        $total = (int)$stmt->fetch()['total'];

        // Get bookings
        $sql = "
            SELECT b.*,
                   h.full_name as helper_name, h.profile_photo as helper_photo, h.work_type,
                   e.full_name as employer_name, u.phone as employer_phone,
                   (SELECT SUM(amount) FROM payments WHERE booking_id = b.id AND status = 'success') as paid_amount
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            JOIN employers e ON b.employer_id = e.id
            JOIN users u ON e.user_id = u.id
            {$whereClause}
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($queryParams);
        $bookings = $stmt->fetchAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $bookings,
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
        $bookingId = (int)$args['id'];

        $stmt = $this->pdo->prepare("
            SELECT b.*,
                   h.full_name as helper_name, h.profile_photo as helper_photo,
                   h.work_type, h.rating_avg, h.badge_level,
                   hu.phone as helper_phone,
                   e.full_name as employer_name, e.location as employer_location,
                   eu.phone as employer_phone
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            JOIN users hu ON h.user_id = hu.id
            JOIN employers e ON b.employer_id = e.id
            JOIN users eu ON e.user_id = eu.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Booking not found'
            ], 404);
        }

        // Get payments
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC");
        $stmt->execute([$bookingId]);
        $booking['payments'] = $stmt->fetchAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'booking' => $booking
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['id'];
        $data = $request->getParsedBody();

        $allowedFields = ['status', 'start_date', 'notes', 'monthly_rate'];
        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'No fields to update'
            ], 400);
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $bookingId;

        $sql = "UPDATE bookings SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Booking updated'
        ]);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['id'];
        $data = $request->getParsedBody();

        $stmt = $this->pdo->prepare("
            UPDATE bookings SET
                status = 'cancelled',
                cancelled_reason = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['reason'] ?? 'Cancelled by admin',
            $bookingId
        ]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Booking cancelled'
        ]);
    }

    public function getStats(Request $request, Response $response): Response
    {
        $stats = [];

        // Status counts
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
        while ($row = $stmt->fetch()) {
            $stats['by_status'][$row['status']] = (int)$row['count'];
        }

        // Today's bookings
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM bookings WHERE DATE(created_at) = DATE('now')");
        $stats['today'] = (int)$stmt->fetch()['count'];

        // This week
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM bookings WHERE created_at >= DATE('now', '-7 days')");
        $stats['this_week'] = (int)$stmt->fetch()['count'];

        // This month
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM bookings WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')");
        $stats['this_month'] = (int)$stmt->fetch()['count'];

        return $this->jsonResponse($response, [
            'success' => true,
            'stats' => $stats
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
