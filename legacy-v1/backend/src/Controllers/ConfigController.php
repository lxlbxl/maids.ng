<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConfigController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSiteConfig(Request $request, Response $response): Response
    {
        $settings = $this->getAllSettings();

        return $this->jsonResponse($response, [
            'success' => true,
            'contact' => json_decode($settings['contact'] ?? '{}', true),
            'serviceFees' => json_decode($settings['service_fee'] ?? $settings['matching_fee_amount'] ?? '{}', true),
            'ninVerificationFee' => json_decode($settings['nin_verification_fee'] ?? '{"amount": 5000, "currency": "NGN"}', true),
            'general' => [
                'siteName' => $_ENV['APP_NAME'] ?? 'Maids.ng',
                'tagline' => 'Find Trusted Domestic Workers in Nigeria'
            ],
            'locations' => json_decode($settings['locations'] ?? '[]', true),
            'workTypes' => json_decode($settings['work_types'] ?? '[]', true),
            'skills' => json_decode($settings['skills'] ?? '[]', true)
        ]);
    }

    public function getContactInfo(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'contact'");
        $stmt->execute();
        $result = $stmt->fetch();

        $contact = $result ? json_decode($result['value'], true) : [
            'phone' => '+234-XXX-XXX-XXXX',
            'whatsapp' => '+234-XXX-XXX-XXXX',
            'email' => 'support@maids.ng'
        ];

        return $this->jsonResponse($response, $contact);
    }

    public function getServiceFees(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'service_fee'");
        $stmt->execute();
        $result = $stmt->fetch();

        $fees = $result ? json_decode($result['value'], true) : [
            'amount' => (int) ($_ENV['SERVICE_FEE_AMOUNT'] ?? 10000),
            'currency' => $_ENV['SERVICE_FEE_CURRENCY'] ?? 'NGN'
        ];

        return $this->jsonResponse($response, $fees);
    }

    public function getLocations(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'locations'");
        $stmt->execute();
        $result = $stmt->fetch();

        $locations = $result ? json_decode($result['value'], true) : [];

        return $this->jsonResponse($response, [
            'success' => true,
            'locations' => $locations
        ]);
    }

    public function getWorkTypes(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'work_types'");
        $stmt->execute();
        $result = $stmt->fetch();

        $workTypes = $result ? json_decode($result['value'], true) : [];

        return $this->jsonResponse($response, [
            'success' => true,
            'work_types' => $workTypes
        ]);
    }

    public function getSkills(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'skills'");
        $stmt->execute();
        $result = $stmt->fetch();

        $skills = $result ? json_decode($result['value'], true) : [];

        return $this->jsonResponse($response, [
            'success' => true,
            'skills' => $skills
        ]);
    }

    public function getPaymentConfig(Request $request, Response $response): Response
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'payment_flutterwave_public'");
        $stmt->execute();
        $result = $stmt->fetch();

        $publicKey = $result ? json_decode($result['value'], true) : null;

        // Also get service fees
        $stmtFee = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = 'service_fee'");
        $stmtFee->execute();
        $resultFee = $stmtFee->fetch();
        $fees = $resultFee ? json_decode($resultFee['value'], true) : [];

        return $this->jsonResponse($response, [
            'success' => true,
            'public_key' => $publicKey,
            'currency' => $fees['currency'] ?? 'NGN',
            'amount' => $fees['amount'] ?? 10000
        ]);
    }

    private function getAllSettings(): array
    {
        $stmt = $this->pdo->query("SELECT key_name, value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key_name']] = $row['value'];
        }
        return $settings;
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
