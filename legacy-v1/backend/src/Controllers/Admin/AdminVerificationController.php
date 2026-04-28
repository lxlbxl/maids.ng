<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\VerificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminVerificationController
{
    private VerificationService $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $limit = min(100, max(1, (int)($params['limit'] ?? 50)));
        $offset = max(0, (int)($params['offset'] ?? 0));

        $verifications = $this->verificationService->getPendingVerifications($limit, $offset);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $verifications
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $verificationId = (int)$args['id'];

        $verification = $this->verificationService->getVerification($verificationId);

        if (!$verification) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Verification not found'
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'verification' => $verification
        ]);
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $verificationId = (int)$args['id'];
        $adminId = $request->getAttribute('admin_id');

        $result = $this->verificationService->approveVerification($verificationId, $adminId);

        return $this->jsonResponse($response, [
            'success' => $result,
            'message' => $result ? 'Verification approved' : 'Approval failed'
        ]);
    }

    public function reject(Request $request, Response $response, array $args): Response
    {
        $verificationId = (int)$args['id'];
        $adminId = $request->getAttribute('admin_id');
        $data = $request->getParsedBody();

        $reason = $data['reason'] ?? 'Rejected by admin';

        $result = $this->verificationService->rejectVerification($verificationId, $adminId, $reason);

        return $this->jsonResponse($response, [
            'success' => $result,
            'message' => $result ? 'Verification rejected' : 'Rejection failed'
        ]);
    }

    public function getStats(Request $request, Response $response): Response
    {
        $stats = $this->verificationService->getVerificationStats();

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
