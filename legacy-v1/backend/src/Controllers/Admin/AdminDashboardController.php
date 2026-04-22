<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\PaymentService;
use App\Services\VerificationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminDashboardController
{
    private PDO $pdo;
    private PaymentService $paymentService;
    private VerificationService $verificationService;

    public function __construct(
        PDO $pdo,
        PaymentService $paymentService,
        VerificationService $verificationService
    ) {
        $this->pdo = $pdo;
        $this->paymentService = $paymentService;
        $this->verificationService = $verificationService;
    }

    public function index(Request $request, Response $response): Response
    {
        $stats = $this->getStats();
        $recentActivity = $this->getRecentActivity();
        $chartData = $this->getChartData();

        return $this->jsonResponse($response, [
            'success' => true,
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'chart_data' => $chartData
        ]);
    }

    private function getStats(): array
    {
        $stats = [];

        // Total helpers
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM helpers WHERE status = 'active'");
        $stats['total_helpers'] = (int) $stmt->fetch()['count'];

        // Verified helpers
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM helpers WHERE verification_status = 'verified'");
        $stats['verified_helpers'] = (int) $stmt->fetch()['count'];

        // Total employers
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM employers");
        $stats['total_employers'] = (int) $stmt->fetch()['count'];

        // Total users
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $stats['total_users'] = (int) $stmt->fetch()['count'];

        // Total bookings
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM bookings");
        $stats['total_bookings'] = (int) $stmt->fetch()['count'];

        // Active bookings
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'");
        $stats['active_bookings'] = (int) $stmt->fetch()['count'];

        // Payment stats
        $paymentStats = $this->paymentService->getPaymentStats();
        $stats['total_revenue'] = $paymentStats['total_revenue'];
        $stats['today_revenue'] = $paymentStats['today_revenue'];
        $stats['month_revenue'] = $paymentStats['month_revenue'];

        // Pending verifications
        $verificationStats = $this->verificationService->getVerificationStats();
        $stats['pending_verifications'] = $verificationStats['pending'] ?? 0;

        // Today's signups
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = DATE('now')");
        $stats['today_signups'] = (int) $stmt->fetch()['count'];

        // This week's bookings
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count FROM bookings
            WHERE created_at >= DATE('now', '-7 days')
        ");
        $stats['week_bookings'] = (int) $stmt->fetch()['count'];

        // Conversion rate (leads to bookings)
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM leads");
        $totalLeads = (int) $stmt->fetch()['count'];
        $stats['conversion_rate'] = $totalLeads > 0
            ? round(($stats['total_bookings'] / $totalLeads) * 100, 1)
            : 0;

        return $stats;
    }

    private function getRecentActivity(): array
    {
        $activities = [];

        // Recent bookings
        $stmt = $this->pdo->query("
            SELECT 'booking' as type, b.reference as title,
                   h.full_name as subtitle, b.status, b.created_at
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        while ($row = $stmt->fetch()) {
            $activities[] = $row;
        }

        // Recent payments
        $stmt = $this->pdo->query("
            SELECT 'payment' as type, p.tx_ref as title,
                   '₦' || p.amount as subtitle, p.status, p.created_at
            FROM payments p
            ORDER BY p.created_at DESC
            LIMIT 5
        ");
        while ($row = $stmt->fetch()) {
            $activities[] = $row;
        }

        // Recent verifications
        $stmt = $this->pdo->query("
            SELECT 'verification' as type, h.full_name as title,
                   v.document_type as subtitle, v.status, v.created_at
            FROM verifications v
            JOIN helpers h ON v.helper_id = h.id
            ORDER BY v.created_at DESC
            LIMIT 5
        ");
        while ($row = $stmt->fetch()) {
            $activities[] = $row;
        }

        // Sort by date
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($activities, 0, 10);
    }

    private function getChartData(): array
    {
        $data = [];

        // Last 7 days bookings
        $stmt = $this->pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM bookings
            WHERE created_at >= DATE('now', '-7 days')
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $bookingsData = [];
        while ($row = $stmt->fetch()) {
            $bookingsData[$row['date']] = (int) $row['count'];
        }
        $data['bookings_7days'] = $bookingsData;

        // Last 7 days revenue
        $stmt = $this->pdo->query("
            SELECT DATE(paid_at) as date, SUM(amount) as total
            FROM payments
            WHERE status = 'success' AND paid_at >= DATE('now', '-7 days')
            GROUP BY DATE(paid_at)
            ORDER BY date
        ");
        $revenueData = [];
        while ($row = $stmt->fetch()) {
            $revenueData[$row['date']] = (int) $row['total'];
        }
        $data['revenue_7days'] = $revenueData;

        // User signups last 7 days
        $stmt = $this->pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM users
            WHERE created_at >= DATE('now', '-7 days')
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $signupsData = [];
        while ($row = $stmt->fetch()) {
            $signupsData[$row['date']] = (int) $row['count'];
        }
        $data['signups_7days'] = $signupsData;

        // Work type distribution
        $stmt = $this->pdo->query("
            SELECT work_type, COUNT(*) as count
            FROM helpers
            WHERE status = 'active'
            GROUP BY work_type
            ORDER BY count DESC
        ");
        $workTypeData = [];
        while ($row = $stmt->fetch()) {
            $workTypeData[$row['work_type']] = (int) $row['count'];
        }
        $data['work_type_distribution'] = $workTypeData;

        // Location distribution
        $stmt = $this->pdo->query("
            SELECT location, COUNT(*) as count
            FROM helpers
            WHERE status = 'active' AND location IS NOT NULL
            GROUP BY location
            ORDER BY count DESC
            LIMIT 10
        ");
        $locationData = [];
        while ($row = $stmt->fetch()) {
            $locationData[$row['location']] = (int) $row['count'];
        }
        $data['location_distribution'] = $locationData;

        return $data;
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
