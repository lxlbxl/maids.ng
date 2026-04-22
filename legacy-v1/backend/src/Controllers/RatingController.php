<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MatchingService;
use App\Services\NotificationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RatingController
{
    private PDO $pdo;
    private MatchingService $matchingService;
    private NotificationService $notificationService;

    public function __construct(
        PDO $pdo,
        MatchingService $matchingService,
        NotificationService $notificationService
    ) {
        $this->pdo = $pdo;
        $this->matchingService = $matchingService;
        $this->notificationService = $notificationService;
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $userId = $request->getAttribute('user_id');

        $helperId = (int)($data['helper_id'] ?? 0);
        $rating = (int)($data['rating'] ?? 0);
        $review = $data['review'] ?? null;
        $bookingId = $data['booking_id'] ?? null;

        if (!$helperId || $rating < 1 || $rating > 5) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Valid helper_id and rating (1-5) are required'
            ], 400);
        }

        // Get employer ID
        $stmt = $this->pdo->prepare("SELECT id, full_name FROM employers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $employer = $stmt->fetch();

        if (!$employer) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Employer profile not found'
            ], 404);
        }

        // Check if already rated this helper for this booking
        if ($bookingId) {
            $stmt = $this->pdo->prepare("SELECT id FROM ratings WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            if ($stmt->fetch()) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'Already rated this booking'
                ], 409);
            }
        }

        // Create rating
        $stmt = $this->pdo->prepare("
            INSERT INTO ratings (helper_id, employer_id, booking_id, rating, review)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $helperId,
            $employer['id'],
            $bookingId,
            $rating,
            $review
        ]);

        $ratingId = (int)$this->pdo->lastInsertId();

        // Update helper's average rating
        $this->matchingService->updateRating($helperId);

        // Get updated helper info for notification
        $helper = $this->matchingService->getHelper($helperId);

        // Send notification
        $this->notificationService->notifyNewRating(
            ['id' => $ratingId, 'rating' => $rating, 'review' => $review],
            $helper,
            $employer
        );

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Rating submitted',
            'rating_id' => $ratingId
        ], 201);
    }

    public function getHelperRatings(Request $request, Response $response, array $args): Response
    {
        $helperId = (int)$args['id'];

        $stmt = $this->pdo->prepare("
            SELECT r.*, e.full_name as employer_name
            FROM ratings r
            LEFT JOIN employers e ON r.employer_id = e.id
            WHERE r.helper_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$helperId]);
        $ratings = $stmt->fetchAll();

        // Calculate stats
        $stmt = $this->pdo->prepare("
            SELECT
                AVG(rating) as average,
                COUNT(*) as total,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM ratings
            WHERE helper_id = ?
        ");
        $stmt->execute([$helperId]);
        $stats = $stmt->fetch();

        return $this->jsonResponse($response, [
            'success' => true,
            'ratings' => $ratings,
            'stats' => [
                'average' => round($stats['average'] ?? 0, 1),
                'total' => (int)($stats['total'] ?? 0),
                'distribution' => [
                    5 => (int)($stats['five_star'] ?? 0),
                    4 => (int)($stats['four_star'] ?? 0),
                    3 => (int)($stats['three_star'] ?? 0),
                    2 => (int)($stats['two_star'] ?? 0),
                    1 => (int)($stats['one_star'] ?? 0)
                ]
            ]
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
