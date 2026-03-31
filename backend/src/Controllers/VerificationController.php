<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\VerificationService;
use App\Services\FileUploadService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VerificationController
{
    private VerificationService $verificationService;
    private FileUploadService $fileUploadService;

    public function __construct(
        VerificationService $verificationService,
        FileUploadService $fileUploadService
    ) {
        $this->verificationService = $verificationService;
        $this->fileUploadService = $fileUploadService;
    }

    public function submitNin(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $helperId = (int)($data['helper_id'] ?? 0);
        $ninNumber = $data['nin_number'] ?? $data['nin'] ?? '';
        $ninImage = $data['nin_image'] ?? $data['nin_image_base64'] ?? '';

        if (!$helperId || !$ninNumber) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Helper ID and NIN number are required'
            ], 400);
        }

        // Validate NIN format (11 digits)
        if (!preg_match('/^\d{11}$/', $ninNumber)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'NIN must be 11 digits'
            ], 400);
        }

        // Upload NIN image if provided
        $documentPath = null;
        if ($ninImage) {
            $uploadResult = $this->fileUploadService->uploadBase64($ninImage, 'documents');
            if ($uploadResult['success']) {
                $documentPath = $uploadResult['path'];
            }
        }

        // Submit verification
        $verificationId = $this->verificationService->submitVerification(
            $helperId,
            'nin',
            $documentPath,
            $ninNumber
        );

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'NIN verification submitted',
            'verification_id' => $verificationId
        ], 201);
    }

    public function getStatus(Request $request, Response $response, array $args): Response
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
            'verification' => [
                'id' => $verification['id'],
                'status' => $verification['status'],
                'document_type' => $verification['document_type'],
                'created_at' => $verification['created_at'],
                'verified_at' => $verification['verified_at'],
                'rejection_reason' => $verification['rejection_reason']
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
