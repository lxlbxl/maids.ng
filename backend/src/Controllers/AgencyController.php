<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AgencyService;
use App\Services\AuthService;
use App\Services\MatchingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AgencyController
{
    private AgencyService $agencyService;
    private AuthService $authService;
    private MatchingService $matchingService;

    public function __construct(AgencyService $agencyService, AuthService $authService, MatchingService $matchingService)
    {
        $this->agencyService = $agencyService;
        $this->authService = $authService;
        $this->matchingService = $matchingService;
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

        // Basic validation
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

        // Verify ownership
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

        // Verify ownership
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

        // Handle PIN update separately via AuthService
        if (!empty($data['pin'])) {
            $this->authService->updatePin($userId, $data['pin']);
        }

        // Update agency profile fields
        $this->agencyService->updateProfile($userId, $data);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Profile updated'
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
