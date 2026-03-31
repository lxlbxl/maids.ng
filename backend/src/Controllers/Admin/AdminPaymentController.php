<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\PaymentService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminPaymentController
{
    private PDO $pdo;
    private PaymentService $paymentService;

    public function __construct(PDO $pdo, PaymentService $paymentService)
    {
        $this->pdo = $pdo;
        $this->paymentService = $paymentService;
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
            $where[] = "p.status = ?";
            $queryParams[] = $params['status'];
        }

        // Gateway filter
        if (!empty($params['gateway'])) {
            $where[] = "p.gateway = ?";
            $queryParams[] = $params['gateway'];
        }

        // Date range
        if (!empty($params['from_date'])) {
            $where[] = "p.created_at >= ?";
            $queryParams[] = $params['from_date'];
        }
        if (!empty($params['to_date'])) {
            $where[] = "p.created_at <= ?";
            $queryParams[] = $params['to_date'] . ' 23:59:59';
        }

        // Search
        if (!empty($params['search'])) {
            $where[] = "(p.tx_ref LIKE ? OR p.gateway_ref LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM payments p {$whereClause}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($queryParams);
        $total = (int)$stmt->fetch()['total'];

        // Get payments
        $sql = "
            SELECT p.*,
                   b.reference as booking_ref,
                   h.full_name as helper_name,
                   eu.phone as employer_phone
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            JOIN helpers h ON b.helper_id = h.id
            JOIN employers e ON b.employer_id = e.id
            JOIN users eu ON e.user_id = eu.id
            {$whereClause}
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($queryParams);
        $payments = $stmt->fetchAll();

        // Parse metadata
        foreach ($payments as &$payment) {
            $payment['metadata'] = json_decode($payment['metadata'] ?? '{}', true);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $payments,
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
        $paymentId = (int)$args['id'];

        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   b.reference as booking_ref, b.status as booking_status,
                   h.full_name as helper_name, h.profile_photo as helper_photo,
                   e.full_name as employer_name, eu.phone as employer_phone
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            JOIN helpers h ON b.helper_id = h.id
            JOIN employers e ON b.employer_id = e.id
            JOIN users eu ON e.user_id = eu.id
            WHERE p.id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Payment not found'
            ], 404);
        }

        $payment['metadata'] = json_decode($payment['metadata'] ?? '{}', true);

        return $this->jsonResponse($response, [
            'success' => true,
            'payment' => $payment
        ]);
    }

    public function refund(Request $request, Response $response, array $args): Response
    {
        $paymentId = (int)$args['id'];
        $data = $request->getParsedBody();

        $result = $this->paymentService->processRefund($paymentId, $data['reason'] ?? '');

        return $this->jsonResponse($response, $result, $result['success'] ? 200 : 400);
    }

    public function getStats(Request $request, Response $response): Response
    {
        $stats = $this->paymentService->getPaymentStats();

        // Add additional stats
        $stmt = $this->pdo->query("SELECT gateway, COUNT(*) as count, SUM(amount) as total FROM payments WHERE status = 'success' GROUP BY gateway");
        $stats['by_gateway'] = [];
        while ($row = $stmt->fetch()) {
            $stats['by_gateway'][$row['gateway']] = [
                'count' => (int)$row['count'],
                'total' => (int)$row['total']
            ];
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'stats' => $stats
        ]);
    }

    public function export(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $where = [];
        $queryParams = [];

        if (!empty($params['from_date'])) {
            $where[] = "p.created_at >= ?";
            $queryParams[] = $params['from_date'];
        }
        if (!empty($params['to_date'])) {
            $where[] = "p.created_at <= ?";
            $queryParams[] = $params['to_date'] . ' 23:59:59';
        }
        if (!empty($params['status'])) {
            $where[] = "p.status = ?";
            $queryParams[] = $params['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT p.tx_ref, p.amount, p.currency, p.gateway, p.status,
                   p.payment_method, p.paid_at, p.created_at,
                   b.reference as booking_ref,
                   h.full_name as helper_name,
                   eu.phone as employer_phone
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            JOIN helpers h ON b.helper_id = h.id
            JOIN employers e ON b.employer_id = e.id
            JOIN users eu ON e.user_id = eu.id
            {$whereClause}
            ORDER BY p.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($queryParams);
        $payments = $stmt->fetchAll();

        // Generate CSV
        $csv = "Transaction Ref,Amount,Currency,Gateway,Status,Payment Method,Paid At,Created At,Booking Ref,Helper Name,Employer Phone\n";
        foreach ($payments as $p) {
            $csv .= "\"{$p['tx_ref']}\",{$p['amount']},{$p['currency']},{$p['gateway']},{$p['status']},";
            $csv .= "\"{$p['payment_method']}\",\"{$p['paid_at']}\",\"{$p['created_at']}\",";
            $csv .= "\"{$p['booking_ref']}\",\"{$p['helper_name']}\",\"{$p['employer_phone']}\"\n";
        }

        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="payments_export.csv"');
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
