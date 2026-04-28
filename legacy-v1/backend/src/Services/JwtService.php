<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Exception;

class JwtService
{
    private string $secretKey;
    private string $algorithm = 'HS256';
    private int $accessTokenTTL;  // seconds
    private int $refreshTokenTTL; // seconds
    private string $issuer;

    public function __construct()
    {
        $this->secretKey = $_ENV['APP_SECRET'] ?? $_SERVER['APP_SECRET'] ?? 'default-secret-change-me';
        $this->issuer = $_ENV['APP_NAME'] ?? 'Maids.ng';

        // Access token: 1 hour, Refresh token: 30 days
        $this->accessTokenTTL = (int) ($_ENV['JWT_ACCESS_TTL'] ?? 3600);
        $this->refreshTokenTTL = (int) ($_ENV['JWT_REFRESH_TTL'] ?? 2592000);
    }

    /**
     * Generate access and refresh tokens for a user
     */
    public function generateTokens(array $user, string $type = 'user'): array
    {
        $now = time();

        $accessPayload = [
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + $this->accessTokenTTL,
            'type' => 'access',
            'sub' => $user['id'],
            'user_type' => $type,
            'data' => [
                'id' => $user['id'],
                'phone' => $user['phone'] ?? null,
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? $user['user_type'] ?? $type,
            ]
        ];

        $refreshPayload = [
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + $this->refreshTokenTTL,
            'type' => 'refresh',
            'sub' => $user['id'],
            'user_type' => $type,
        ];

        return [
            'access_token' => JWT::encode($accessPayload, $this->secretKey, $this->algorithm),
            'refresh_token' => JWT::encode($refreshPayload, $this->secretKey, $this->algorithm),
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenTTL,
            'refresh_expires_in' => $this->refreshTokenTTL,
        ];
    }

    /**
     * Validate and decode a token
     */
    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return json_decode(json_encode($decoded), true);
        } catch (ExpiredException $e) {
            return ['error' => 'Token expired', 'code' => 'TOKEN_EXPIRED'];
        } catch (SignatureInvalidException $e) {
            return ['error' => 'Invalid signature', 'code' => 'INVALID_SIGNATURE'];
        } catch (Exception $e) {
            return ['error' => 'Invalid token', 'code' => 'INVALID_TOKEN'];
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(string $refreshToken): ?array
    {
        $payload = $this->validateToken($refreshToken);

        if (!$payload || isset($payload['error'])) {
            return null;
        }

        if (($payload['type'] ?? '') !== 'refresh') {
            return null;
        }

        // Generate new tokens (rotation)
        $user = [
            'id' => $payload['sub'],
            'phone' => $payload['data']['phone'] ?? null,
            'email' => $payload['data']['email'] ?? null,
            'role' => $payload['data']['role'] ?? $payload['user_type'] ?? 'user',
        ];

        return $this->generateTokens($user, $payload['user_type'] ?? 'user');
    }

    /**
     * Extract token from Authorization header
     */
    public function extractTokenFromHeader(string $authHeader): ?string
    {
        if (empty($authHeader)) {
            return null;
        }

        // Check for Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get user ID from token payload
     */
    public function getUserIdFromToken(array $payload): ?int
    {
        return isset($payload['sub']) ? (int) $payload['sub'] : null;
    }

    /**
     * Check if token is an access token
     */
    public function isAccessToken(array $payload): bool
    {
        return ($payload['type'] ?? '') === 'access';
    }

    /**
     * Check if token is a refresh token
     */
    public function isRefreshToken(array $payload): bool
    {
        return ($payload['type'] ?? '') === 'refresh';
    }
}
