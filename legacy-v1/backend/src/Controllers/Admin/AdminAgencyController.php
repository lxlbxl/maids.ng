<?php

namespace App\Controllers\Admin;

use App\Services\AgencyService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminAgencyController
{
    private AgencyService $agencyService;

    public function __construct(AgencyService $agencyService)
    {
        $this->agencyService = $agencyService;
    }

    public function getAllAgencies(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(50, max(1, (int) ($params['limit'] ?? 10)));
        $search = $params['search'] ?? '';

        $result = $this->agencyService->getAllAgencies($page, $limit, $search);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $result['agencies'], // Frontend expects 'data' often, sticking to convention
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'limit' => $result['limit'],
                'pages' => $result['pages'],
            ]
        ]);
    }

    public function getAgencyDetails(Request $request, Response $response, array $args): Response
    {
        $agencyId = (int) $args['id'];

        $agency = $this->agencyService->getAgencyDetails($agencyId);

        if (!$agency) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Agency not found'
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'agency' => $agency
        ]);
    }

    public function verifyAgency(Request $request, Response $response, array $args): Response
    {
        $agencyId = (int) $args['id'];
        $data = $request->getParsedBody();
        $status = $data['status'] ?? 'active';

        if (!$this->agencyService->isValidStatus($status)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Invalid status. Allowed: active, suspended, pending, inactive'
            ], 400);
        }

        $this->agencyService->updateAgencyStatus($agencyId, $status);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Agency status updated to ' . $status
        ]);
    }

    public function deleteAgency(Request $request, Response $response, array $args): Response
    {
        $agencyId = (int) $args['id'];

        $result = $this->agencyService->deleteAgencyWithCleanup($agencyId);

        if (!$result) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to delete agency'
            ], 500);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Agency and associated data deleted'
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        // Alias for getAllAgencies to match route definition if needed
        return $this->getAllAgencies($request, $response);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
