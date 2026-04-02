<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AgencyService;
use App\Services\AuthService;
use App\Services\MatchingService;
use App\Services\NotificationService;
use App\Controllers\NotificationController;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AgencyController
{
    private AgencyService $agencyService;
    private AuthService $authService;
    private MatchingService $matchingService;
    private NotificationService $notificationService;
    private PDO $pdo;

    public function __construct(
        AgencyService $agencyService,
        AuthService $authService,
        MatchingService $matchingService,
        NotificationService $notificationService,
        PDO $pdo
    ) {
        $this->agencyService = $agencyService;
        $this->authService = $authService;
        $this->matchingService = $matchingService;
        $this->notificationService = $notificationService;
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $stats = $this->agencyService->getDashboardStats($userId);
        $recentResult = $this->agencyService->getAgencyMaids($userId, 1, 5);
        return $this->jsonResponse($response, [
            'success' => true,
            'stats' => $stats,
            'recent_maids' => $recentResult['data']
        ]);
    }

    public function getMaids(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 10);
        $result = $this->agencyService->getAgencyMaids($userId, $page, $limit);
        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta']
        ]);
    }

    public function addMaid(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['full_name']) || empty($data['salary_min'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Full Name and Minimum Salary are required'
            ], 400);
        }

        try {
            $helperId = $this->matchingService->createHelper($userId, $data);
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Maid added successfully',
                'helper_id' => $helperId
            ], 201);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to add maid: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateMaid(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();

        $helper = $this->matchingService->getHelper($helperId);
        if (!$helper || $helper['user_id'] !== $userId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Maid not found or unauthorized'
            ], 404);
        }

        $success = $this->matchingService->updateHelper($helperId, $data);

        if ($success) {
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Maid updated successfully'
            ]);
        } else {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to update maid'
            ], 500);
        }
    }

    public function deleteMaid(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $helperId = (int) $args['id'];

        $helper = $this->matchingService->getHelper($helperId);
        if (!$helper || $helper['user_id'] !== $userId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Maid not found or unauthorized'
            ], 404);
        }

        $this->agencyService->softDeleteMaid($helperId);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Maid removed successfully'
        ]);
    }

    public function getProfile(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $profile = $this->agencyService->getProfile($userId);
        if (!$profile) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Agency not found'
            ], 404);
        }
        return $this->jsonResponse($response, [
            'success' => true,
            'profile' => $profile
        ]);
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (!empty($data['pin'])) {
            $this->authService->updatePin($userId, $data['pin']);
        }
        $this->agencyService->updateProfile($userId, $data);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Profile updated'
        ]);
    }

    // New endpoints:

    public function getPendingBookings(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $stmt = $this->pdo->prepare("SELECT id FROM agency_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $agency = $stmt->fetch();
        if (!$agency) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Agency not found'], 404);
        }
        $stmt = $this->pdo->prepare("
            SELECT b.*, e.full_name as employer_name, e.location as employer_location, h.full_name as helper_name, h.work_type
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            JOIN employers e ON b.employer_id = e.id
            JOIN users hu ON h.user_id = hu.id
            WHERE hu.id = ? AND b.status = 'pending'
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$userId]);
        $bookings = $stmt->fetchAll();
        return $this->jsonResponse($response, [
            'success' => true,
            'requests' => $bookings
        ]);
    }

    public function approveBooking(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['id'];
        $userId = $request->getAttribute('user_id');

        $stmt = $this->pdo->prepare("
            SELECT b.id FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            JOIN users hu ON h.user_id = hu.id
            WHERE b.id = ? AND hu.id = ? AND b.status = 'pending'
        ");
        $stmt->execute([$bookingId, $userId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Booking not found or unauthorized'
            ], 404);
        }

        $stmt = $this->pdo->prepare("UPDATE bookings SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$bookingId]);

        $stmt = $this->pdo->prepare("
            SELECT e.user_id, u.phone FROM employers e JOIN users u ON e.user_id = u.id WHERE e.id = ?
        ");
        $stmt->execute([$booking['employer_id']]);
        $employer = $stmt->fetch();

        if ($employer) {
            NotificationController::send($this->pdo, (int)$employer['user_id'], 'booking.approved', 'email', 'Booking Approved', 'Your booking request has been approved by the agency.', ['booking_id' => $bookingId]);
            try {
                $this->notificationService->notifyBookingApprovedByAgency($booking, [
                    'email' => null,
                    'phone' => $employer['phone'],
                    'full_name' => $employer['full_name'] ?? 'Employer'
                ]);
            } catch (\Throwable $e) {}
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Booking approved'
        ]);
    }

    public function rejectBooking(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['id'];
        $userId = $request->getAttribute('user_id');

        $stmt = $this->pdo->prepare("
            SELECT b.id FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            JOIN users hu ON h.user_id = hu.id
            WHERE b.id = ? AND hu.id = ? AND b.status = 'pending'
        ");
        $stmt->execute([$bookingId, $userId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Booking not found or unauthorized'
            ], 404);
        }

        $stmt = $this->pdo->prepare("UPDATE bookings SET status = 'declined', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$bookingId]);

        $stmt = $this->pdo->prepare("SELECT e.user_id FROM employers e WHERE e.id = ?");
        $stmt->execute([$booking['employer_id']]);
        $employer = $stmt->fetch();
        if ($employer) {
            NotificationController::send($this->pdo, (int)$employer['user_id'], 'booking.rejected', 'email', 'Booking Rejected', 'Your booking request was rejected by the agency.', ['booking_id' => $bookingId]);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Booking rejected'
        ]);
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
