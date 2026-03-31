<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminAuthController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Email and password are required'
            ], 400);
        }

        $admin = $this->authService->adminLogin($email, $password);

        if (!$admin) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Invalid credentials'
            ], 401);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Login successful',
            'admin' => $admin
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->authService->adminLogout();

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request, Response $response): Response
    {
        $adminId = $request->getAttribute('admin_id');
        $adminEmail = $request->getAttribute('admin_email');
        $adminName = $request->getAttribute('admin_name');
        $permissions = $request->getAttribute('admin_permissions');

        return $this->jsonResponse($response, [
            'success' => true,
            'admin' => [
                'id' => $adminId,
                'email' => $adminEmail,
                'name' => $adminName,
                'role' => $_SESSION['admin_role_name'] ?? null,
                'permissions' => $permissions
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
