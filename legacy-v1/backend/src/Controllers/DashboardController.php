<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $userType = $request->getAttribute('user_type');
        $params = $request->getQueryParams();

        // Support legacy phone-based lookup
        $phone = $params['phone'] ?? null;

        if ($phone) {
            $stmt = $this->pdo->prepare("SELECT id, user_type FROM users WHERE phone = ?");
            $stmt->execute([$this->normalizePhone($phone)]);
            $user = $stmt->fetch();
            if ($user) {
                $userId = $user['id'];
                $userType = $user['user_type'];
            }
        }

        if (!$userId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }

        if ($userType === 'helper') {
            return $this->getHelperDashboard($response, $userId);
        }

        return $this->getEmployerDashboard($response, $userId);
    }

    private function getEmployerDashboard(Response $response, int $userId): Response
    {
        // Get user info
        $stmt = $this->pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // Get employer info
        $stmt = $this->pdo->prepare("SELECT * FROM employers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $employer = $stmt->fetch();

        if (!$employer) {
            return $this->jsonResponse($response, [
                'success' => true,
                'user_name' => 'User',
                'phone' => $user['phone'] ?? '',
                'active_maids' => [],
                'total_salary' => 0,
                'average_rating' => 0,
                'activities' => []
            ]);
        }

        // Get active maids (confirmed bookings)
        $stmt = $this->pdo->prepare("
            SELECT h.id, h.full_name as name, h.profile_photo as photo,
                   h.work_type, h.rating_avg, h.badge_level,
                   b.status, b.monthly_rate as rate, b.start_date
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            WHERE b.employer_id = ? AND b.status = 'confirmed'
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$employer['id']]);
        $activeMaids = $stmt->fetchAll();

        // Calculate total monthly salary
        $totalSalary = array_sum(array_column($activeMaids, 'rate'));

        // Calculate average rating of hired maids
        $ratings = array_filter(array_column($activeMaids, 'rating_avg'));
        $avgRating = count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 0;

        // Get recent activities
        $stmt = $this->pdo->prepare("
            SELECT 'booking' as type, b.reference, h.full_name as maid_name,
                   b.service_fee as amount, b.created_at as date, b.status
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            WHERE b.employer_id = ?
            ORDER BY b.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$employer['id']]);
        $activities = $stmt->fetchAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'user_name' => $employer['full_name'] ?? 'User',
            'phone' => $user['phone'] ?? '',
            'active_maids' => $activeMaids,
            'total_salary' => $totalSalary,
            'average_rating' => $avgRating,
            'activities' => $activities
        ]);
    }

    private function getHelperDashboard(Response $response, int $userId): Response
    {
        // Get helper info
        $stmt = $this->pdo->prepare("
            SELECT h.*, u.phone FROM helpers h
            JOIN users u ON h.user_id = u.id
            WHERE h.user_id = ?
        ");
        $stmt->execute([$userId]);
        $helper = $stmt->fetch();

        if (!$helper) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Helper profile not found'
            ], 404);
        }

        // Get bookings for this helper
        $stmt = $this->pdo->prepare("
            SELECT b.*, e.full_name as employer_name
            FROM bookings b
            JOIN employers e ON b.employer_id = e.id
            WHERE b.helper_id = ? AND b.status = 'confirmed'
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$helper['id']]);
        $bookings = $stmt->fetchAll();

        // Get ratings
        $stmt = $this->pdo->prepare("
            SELECT r.*, e.full_name as employer_name
            FROM ratings r
            JOIN employers e ON r.employer_id = e.id
            WHERE r.helper_id = ?
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$helper['id']]);
        $ratings = $stmt->fetchAll();

        // Calculate earnings
        $totalEarnings = array_sum(array_column($bookings, 'monthly_rate'));

        return $this->jsonResponse($response, [
            'success' => true,
            'helper' => [
                'id' => $helper['id'],
                'name' => $helper['full_name'],
                'phone' => $helper['phone'],
                'work_type' => $helper['work_type'],
                'rating_avg' => $helper['rating_avg'],
                'rating_count' => $helper['rating_count'],
                'badge_level' => $helper['badge_level'],
                'verification_status' => $helper['verification_status'],
                'profile_photo' => $helper['profile_photo']
            ],
            'active_jobs' => count($bookings),
            'total_earnings' => $totalEarnings,
            'bookings' => $bookings,
            'ratings' => $ratings
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
