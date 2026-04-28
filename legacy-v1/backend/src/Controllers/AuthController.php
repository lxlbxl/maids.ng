<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\JwtService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private AuthService $authService;
    private JwtService $jwtService;

    public function __construct(AuthService $authService, JwtService $jwtService)
    {
        $this->authService = $authService;
        $this->jwtService = $jwtService;
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $phone = $data['phone'] ?? $data['whatsapp'] ?? '';
        $pin = $data['pin'] ?? '';

        if (empty($phone) || empty($pin)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone and PIN are required'
            ], 400);
        }

        $user = $this->authService->login($phone, $pin);

        if (!$user) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Invalid phone or PIN'
            ], 401);
        }

        // Generate JWT tokens for mobile app support
        $tokens = $this->jwtService->generateTokens($user, 'user');

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'tokens' => $tokens
        ]);
    }

    public function adminLogin(Request $request, Response $response): Response
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

        // Generate JWT tokens
        $tokens = $this->jwtService->generateTokens($admin, 'admin');

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Admin login successful',
            'user' => $admin, // Frontend expects 'user' key often
            'tokens' => $tokens
        ]);
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $phone = $data['phone'] ?? $data['whatsapp'] ?? '';
        $pin = $data['pin'] ?? '';
        $userType = $data['user_type'] ?? 'employer';

        if (empty($phone) || empty($pin)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone and PIN are required'
            ], 400);
        }

        if (strlen($pin) < 4 || strlen($pin) > 6) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'PIN must be 4-6 digits'
            ], 400);
        }

        $user = $this->authService->register($phone, $pin, $userType);

        if (!$user) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone number already registered'
            ], 409);
        }

        // Generate JWT tokens for mobile app support
        $tokens = $this->jwtService->generateTokens($user, 'user');

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Registration successful',
            'user' => $user,
            'tokens' => $tokens
        ], 201);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Not authenticated'
            ], 401);
        }

        $user = $this->authService->getUser($userId);

        return $this->jsonResponse($response, [
            'success' => true,
            'user' => $user,
            'auth_method' => $request->getAttribute('auth_method', 'session')
        ]);
    }

    public function refreshToken(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Refresh token is required'
            ], 400);
        }

        $newTokens = $this->jwtService->refreshAccessToken($refreshToken);

        if (!$newTokens) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Invalid or expired refresh token'
            ], 401);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Token refreshed successfully',
            'tokens' => $newTokens
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
