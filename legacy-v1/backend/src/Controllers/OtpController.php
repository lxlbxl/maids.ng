<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\OtpService;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class OtpController
{
    private OtpService $otpService;
    private AuthService $authService;

    public function __construct(OtpService $otpService, AuthService $authService)
    {
        $this->otpService = $otpService;
        $this->authService = $authService;
    }

    /**
     * Send OTP to phone number
     */
    public function sendToPhone(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $phone = $data['phone'] ?? '';
        $purpose = $data['purpose'] ?? 'verification';

        if (empty($phone)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone number is required'
            ], 400);
        }

        // Validate purpose
        $allowedPurposes = ['verification', 'pin_reset', 'login'];
        if (!in_array($purpose, $allowedPurposes)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Invalid purpose'
            ], 400);
        }

        $result = $this->otpService->sendToPhone($phone, $purpose);

        return $this->jsonResponse(
            $response,
            $result,
            $result['success'] ? 200 : 429
        );
    }

    /**
     * Send OTP to email
     */
    public function sendToEmail(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $purpose = $data['purpose'] ?? 'verification';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Valid email address is required'
            ], 400);
        }

        $result = $this->otpService->sendToEmail($email, $purpose);

        return $this->jsonResponse(
            $response,
            $result,
            $result['success'] ? 200 : 429
        );
    }

    /**
     * Verify OTP
     */
    public function verify(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $identifier = $data['phone'] ?? $data['email'] ?? $data['identifier'] ?? '';
        $otp = $data['otp'] ?? $data['code'] ?? '';
        $purpose = $data['purpose'] ?? 'verification';

        if (empty($identifier)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone number or email is required'
            ], 400);
        }

        if (empty($otp)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'OTP code is required'
            ], 400);
        }

        $result = $this->otpService->verify($identifier, $otp, $purpose);

        return $this->jsonResponse(
            $response,
            $result,
            $result['success'] ? 200 : 400
        );
    }

    /**
     * Reset PIN using OTP
     */
    public function resetPin(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $phone = $data['phone'] ?? '';
        $otp = $data['otp'] ?? '';
        $newPin = $data['new_pin'] ?? '';

        if (empty($phone) || empty($otp) || empty($newPin)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone, OTP, and new PIN are required'
            ], 400);
        }

        // Validate new PIN
        if (!preg_match('/^\d{4,6}$/', $newPin)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'PIN must be 4-6 digits'
            ], 400);
        }

        // Verify OTP first
        $verifyResult = $this->otpService->verify($phone, $otp, 'pin_reset');

        if (!$verifyResult['success']) {
            return $this->jsonResponse($response, $verifyResult, 400);
        }

        // Get user and update PIN
        $user = $this->authService->getUserByPhone($phone);

        if (!$user) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'User not found'
            ], 404);
        }

        $updated = $this->authService->updatePin($user['id'], $newPin);

        if (!$updated) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to update PIN'
            ], 500);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'PIN reset successfully. You can now login with your new PIN.'
        ]);
    }

    /**
     * Request PIN reset (sends OTP)
     */
    public function requestPinReset(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $phone = $data['phone'] ?? '';

        if (empty($phone)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone number is required'
            ], 400);
        }

        // Check if user exists
        $user = $this->authService->getUserByPhone($phone);

        if (!$user) {
            // Don't reveal if user exists or not for security
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'If an account exists with this phone number, you will receive an OTP.'
            ]);
        }

        $result = $this->otpService->sendToPhone($phone, 'pin_reset');

        // Always return success message for security
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'If an account exists with this phone number, you will receive an OTP.',
            'expires_in' => $result['expires_in'] ?? 600,
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
