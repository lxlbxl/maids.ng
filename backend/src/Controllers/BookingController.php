<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MatchingService;
use App\Services\PaymentService;
use App\Services\NotificationService;
use App\Controllers\NotificationController;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;

class BookingController
{
    private PDO $pdo;
    private MatchingService $matchingService;
    private PaymentService $paymentService;
    private NotificationService $notificationService;

    public function __construct(
        PDO $pdo,
        MatchingService $matchingService,
        PaymentService $paymentService,
        NotificationService $notificationService
    ) {
        $this->pdo = $pdo;
        $this->matchingService = $matchingService;
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');

        $helperId = (int)($data['helper_id'] ?? 0);
        if (!$helperId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Helper ID is required'
            ], 400);
        }

        // Get or create employer record
        $stmt = $this->pdo->prepare("SELECT id FROM employers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $employer = $stmt->fetch();

        if (!$employer) {
            // Create employer record
            $stmt = $this->pdo->prepare("
                INSERT INTO employers (user_id, location, help_type, accommodation_preference, budget_min, budget_max, start_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $data['location'] ?? null,
                $data['help_type'] ?? null,
                $data['accommodation'] ?? null,
                $data['budget_min'] ?? null,
                $data['budget_max'] ?? null,
                $data['start_date'] ?? null
            ]);
            $employerId = (int)$this->pdo->lastInsertId();
        } else {
            $employerId = $employer['id'];
        }

        // Get helper details
        $helper = $this->matchingService->getHelper($helperId);
        if (!$helper) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Helper not found'
            ], 404);
        }

        // Create booking
        $reference = 'MAID_' . strtoupper(substr(Uuid::uuid4()->toString(), 0, 8));
        $serviceFee = (int)($_ENV['SERVICE_FEE_AMOUNT'] ?? 10000);

        $stmt = $this->pdo->prepare("
            INSERT INTO bookings (reference, employer_id, helper_id, status, service_fee, monthly_rate, start_date, notes)
            VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $reference,
            $employerId,
            $helperId,
            $serviceFee,
            $helper['salary_min'] ?? null,
            $data['start_date'] ?? null,
            $data['notes'] ?? null
        ]);

        $bookingId = (int)$this->pdo->lastInsertId();

        // Create payment record
        $paymentData = $this->paymentService->createPayment($bookingId, $data['gateway'] ?? 'flutterwave');

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Booking created',
            'booking' => [
                'id' => $bookingId,
                'reference' => $reference,
                'status' => 'pending',
                'service_fee' => $serviceFee
            ],
            'payment' => $paymentData
        ], 201);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['id'];

        $stmt = $this->pdo->prepare("
            SELECT b.*,
                   h.full_name as helper_name, h.profile_photo as helper_photo,
                   h.work_type, h.location as helper_location,
                   e.full_name as employer_name
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            JOIN employers e ON b.employer_id = e.id
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

        return $this->jsonResponse($response, [
            'success' => true,
            'booking' => $booking
        ]);
    }

    public function getUserBookings(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $stmt = $this->pdo->prepare("
            SELECT b.*,
                   h.full_name as helper_name, h.profile_photo as helper_photo,
                   h.work_type, h.rating_avg, h.badge_level
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            JOIN employers e ON b.employer_id = e.id
            WHERE e.user_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$userId]);
        $bookings = $stmt->fetchAll();

        return $this->jsonResponse($response, [
            'success' => true,
            'bookings' => $bookings
        ]);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['id'];
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');

        // Verify ownership
        $stmt = $this->pdo->prepare("
            SELECT b.* FROM bookings b
            JOIN employers e ON b.employer_id = e.id
            WHERE b.id = ? AND e.user_id = ?
        ");
        $stmt->execute([$bookingId, $userId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Booking not found or unauthorized'
            ], 404);
        }

        if (!in_array($booking['status'], ['pending', 'confirmed'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Booking cannot be cancelled'
            ], 400);
        }

        $stmt = $this->pdo->prepare("
            UPDATE bookings SET
                status = 'cancelled',
                cancelled_reason = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$data['reason'] ?? null, $bookingId]);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Booking cancelled'
        ]);
    }

    public function getIncomingRequests(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $stmt = $this->pdo->prepare("SELECT id FROM helpers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $helper = $stmt->fetch();
        if (!$helper) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Helper profile not found'], 404);
        }
        $helperId = (int)$helper['id'];
        $stmt = $this->pdo->prepare("
            SELECT b.*, e.full_name as employer_name, e.location as employer_location, h.full_name as helper_name
            FROM bookings b
            JOIN helpers h ON b.helper_id = h.id
            JOIN employers e ON b.employer_id = e.id
            WHERE b.helper_id = ? AND b.status IN ('pending', 'confirmed')
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$helperId]);
        $bookings = $stmt->fetchAll();
        return $this->jsonResponse($response, ['success'=>true,'requests'=>$bookings]);
    }

    public function accept(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['id'];
        $userId = $request->getAttribute('user_id');
        $stmt = $this->pdo->prepare("SELECT id FROM helpers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $helper = $stmt->fetch();
        if (!$helper) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Helper profile not found'], 404);
        }
        $helperId = (int)$helper['id'];
        $stmt = $this->pdo->prepare("SELECT b.* FROM bookings b WHERE b.id = ? AND b.helper_id = ? AND b.status = 'pending'");
        $stmt->execute([$bookingId, $helperId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Booking not found or cannot be accepted'], 404);
        }
        $stmt = $this->pdo->prepare("UPDATE bookings SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$bookingId]);

        $bookingData = $booking;
        $helperData = ['id'=>$helperId, 'full_name'=>$booking['helper_name'] ?? '', 'email'=>null, 'phone'=>null];
        $employerData = ['id'=>$booking['employer_id'] ?? 0, 'full_name'=>$booking['employer_name'] ?? '', 'email'=>null, 'phone'=>null];

        $stmt = $this->pdo->prepare("SELECT u.phone FROM employers e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
        $stmt->execute([$booking['employer_id']]);
        $emp = $stmt->fetch();
        if ($emp) { $employerData['phone'] = $emp['phone']; }

        NotificationController::send($this->pdo, (int)$employerData['id'], 'booking.accepted', 'email', 'Booking Accepted', 'Your booking has been accepted by the helper. Proceed to payment.', ['booking_id'=>$bookingId, 'reference'=>$booking['reference']]);

        try {
            $this->notificationService->notifyBookingAccepted($bookingData, $helperData, $employerData);
        } catch (\Throwable $e) {
            $this->logger?->error('Notification failed: booking.accepted', ['exception'=>$e]);
        }

        return $this->jsonResponse($response, ['success'=>true,'message'=>'Booking accepted']);
    }

    public function decline(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['id'];
        $userId = $request->getAttribute('user_id');
        $stmt = $this->pdo->prepare("SELECT id FROM helpers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $helper = $stmt->fetch();
        if (!$helper) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Helper profile not found'], 404);
        }
        $helperId = (int)$helper['id'];
        $stmt = $this->pdo->prepare("SELECT b.* FROM bookings b WHERE b.id = ? AND b.helper_id = ? AND b.status = 'pending'");
        $stmt->execute([$bookingId, $helperId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            return $this->jsonResponse($response, ['success'=>false,'error'=>'Booking not found or cannot be declined'], 404);
        }
        $stmt = $this->pdo->prepare("UPDATE bookings SET status = 'declined', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$bookingId]);

        $bookingData = $booking;
        $helperData = ['id'=>$helperId, 'full_name'=>$booking['helper_name'] ?? '', 'email'=>null, 'phone'=>null];
        $employerData = ['id'=>$booking['employer_id'] ?? 0, 'full_name'=>$booking['employer_name'] ?? '', 'email'=>null, 'phone'=>null];

        $stmt = $this->pdo->prepare("SELECT u.phone FROM employers e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
        $stmt->execute([$booking['employer_id']]);
        $emp = $stmt->fetch();
        if ($emp) { $employerData['phone'] = $emp['phone']; }

        NotificationController::send($this->pdo, (int)$employerData['id'], 'booking.declined', 'email', 'Booking Declined', 'Your booking request was declined by the helper.', ['booking_id'=>$bookingId]);

        try {
            $this->notificationService->notifyBookingRejected($bookingData, $helperData, $employerData);
        } catch (\Throwable $e) {
            $this->logger?->error('Notification failed: booking.declined', ['exception'=>$e]);
        }

        return $this->jsonResponse($response, ['success'=>true,'message'=>'Booking declined']);
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
