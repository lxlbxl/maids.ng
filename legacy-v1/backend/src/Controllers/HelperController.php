<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\MatchingService;
use App\Services\FileUploadService;
use App\Services\VerificationService;
use App\Services\PaymentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HelperController
{
    private AuthService $authService;
    private MatchingService $matchingService;
    private FileUploadService $fileUploadService;
    private VerificationService $verificationService;
    private PaymentService $paymentService;

    public function __construct(
        AuthService $authService,
        MatchingService $matchingService,
        FileUploadService $fileUploadService,
        VerificationService $verificationService,
        PaymentService $paymentService
    ) {
        $this->authService = $authService;
        $this->matchingService = $matchingService;
        $this->fileUploadService = $fileUploadService;
        $this->verificationService = $verificationService;
        $this->paymentService = $paymentService;
    }

    public function match(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $criteria = [
            'help_type' => $params['helpType'] ?? $params['help_type'] ?? null,
            'work_type' => $params['workType'] ?? $params['work_type'] ?? null,
            'accommodation' => $params['accommodation'] ?? null,
            'location' => $params['location'] ?? null,
            'budget_min' => $params['budgetMin'] ?? $params['budget_min'] ?? null,
            'budget_max' => $params['budgetMax'] ?? $params['budget_max'] ?? null,
            'start_date' => $params['startDate'] ?? $params['start_date'] ?? null,
        ];

        $limit = (int) ($params['limit'] ?? 3);
        $helpers = $this->matchingService->findMatches($criteria, $limit);

        // Format response to match existing frontend expectations
        return $this->jsonResponse($response, [
            'success' => true,
            'maids' => $helpers,
            'count' => count($helpers)
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $helper = $this->matchingService->getHelper($helperId);

        if (!$helper) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Helper not found'
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'helper' => $helper
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // First register user if phone/pin provided
        $phone = $data['phone'] ?? $data['whatsapp'] ?? null;
        $pin = $data['pin'] ?? null;

        if (!$phone) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Phone number is required'
            ], 400);
        }

        // Check if user exists
        $user = $this->authService->getUserByPhone($phone);

        if (!$user) {
            // PIN is required for new registrations - no default for security
            if (!$pin) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'PIN is required for new registration'
                ], 400);
            }

            // Validate PIN format (4-6 digits)
            if (!preg_match('/^\d{4,6}$/', $pin)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'PIN must be 4-6 digits'
                ], 400);
            }

            // Register new user
            $user = $this->authService->register($phone, $pin, 'helper');
            if (!$user) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'Failed to create user account'
                ], 500);
            }
        }

        // Handle profile photo upload
        $profilePhoto = null;
        if (!empty($data['profile_photo_base64'])) {
            $uploadResult = $this->fileUploadService->uploadBase64($data['profile_photo_base64'], 'profiles');
            if ($uploadResult['success']) {
                $profilePhoto = $uploadResult['path'];
            }
        }

        // Create helper profile
        $helperData = [
            'full_name' => $data['full_name'] ?? $data['name'] ?? '',
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'work_type' => $data['work_type'] ?? $data['workType'] ?? 'Fulltime Maid',
            'accommodation' => $data['accommodation'] ?? null,
            'location' => $data['location'] ?? null,
            'location_state' => $data['location_state'] ?? null,
            'location_lga' => $data['location_lga'] ?? null,
            'salary_min' => $data['salary_min'] ?? $data['expected_salary'] ?? 30000,
            'salary_max' => $data['salary_max'] ?? $data['expected_salary'] ?? 60000,
            'availability' => $data['availability'] ?? 'Immediately',
            'availability_date' => $data['availability_date'] ?? null,
            'experience' => $data['experience'] ?? null,
            'experience_years' => $data['experience_years'] ?? 0,
            'skills' => $data['skills'] ?? [],
            'profile_photo' => $profilePhoto,
            'bio' => $data['bio'] ?? null,
            'languages' => $data['languages'] ?? ['English'],
            'nin_number' => $data['nin_number'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'guarantor_name' => $data['guarantor_name'] ?? null,
            'guarantor_phone' => $data['guarantor_phone'] ?? null,
            'guarantor_relationship' => $data['guarantor_relationship'] ?? null,
            'guarantor_address' => $data['guarantor_address'] ?? null,
            'guarantor_nin' => $data['guarantor_nin'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
        ];

        $helperId = $this->matchingService->createHelper($user['id'], $helperData);

        // Handle NIN verification if provided
        if (!empty($data['nin_number']) && !empty($data['nin_image_base64'])) {
            $ninUpload = $this->fileUploadService->uploadBase64($data['nin_image_base64'], 'documents');
            if ($ninUpload['success']) {
                $this->verificationService->submitVerification(
                    $helperId,
                    'nin',
                    $ninUpload['path'],
                    $data['nin_number']
                );
            }
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Helper profile created successfully',
            'helper_id' => $helperId,
            'user_id' => $user['id']
        ], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();

        // Verify ownership or admin access
        $userId = $request->getAttribute('user_id');
        $helper = $this->matchingService->getHelper($helperId);

        if (!$helper) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Helper not found'
            ], 404);
        }

        if ($helper['user_id'] !== $userId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Unauthorized'
            ], 403);
        }

        // Handle profile photo update
        if (!empty($data['profile_photo_base64'])) {
            $uploadResult = $this->fileUploadService->uploadBase64($data['profile_photo_base64'], 'profiles');
            if ($uploadResult['success']) {
                $data['profile_photo'] = $uploadResult['path'];
                // Delete old photo if exists
                if ($helper['profile_photo']) {
                    $this->fileUploadService->delete($helper['profile_photo']);
                }
            }
        }

        $result = $this->matchingService->updateHelper($helperId, $data);

        return $this->jsonResponse($response, [
            'success' => $result,
            'message' => $result ? 'Profile updated' : 'Update failed'
        ]);
    }

    public function saveBankDetails(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();

        // Verify ownership
        $userId = $request->getAttribute('user_id');
        $helper = $this->matchingService->getHelper($helperId);

        if (!$helper) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Helper not found'], 404);
        }

        if ($helper['user_id'] !== $userId) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $bankCode = $data['bank_code'] ?? null;
        $accountNumber = $data['account_number'] ?? null;

        if (!$bankCode || !$accountNumber) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Bank code and account number are required'], 400);
        }

        try {
            // 1. Resolve Account Name
            $resolverResponse = $this->paymentService->resolveAccount($accountNumber, $bankCode);
            if (!$resolverResponse['success']) {
                return $this->jsonResponse($response, ['success' => false, 'error' => 'Could not resolve account name'], 400);
            }

            $accountName = $resolverResponse['data']['account_name'];

            // 2. Create Subaccount with Payment Gateway
            $bankDetails = [
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'account_name' => $accountName
            ];

            $subaccount = $this->paymentService->createSubaccount($bankDetails, $helper);

            // 3. Save to Database
            $updateData = [
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'subaccount_id' => $subaccount['subaccount_id'] ?? $subaccount['id']
            ];

            $this->matchingService->updateHelper($helperId, $updateData);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Bank details saved successfully',
                'subaccount_id' => $updateData['subaccount_id']
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateAvailability(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $data = $request->getParsedBody();
        $schedule = $data['schedule'] ?? [];

        // Verify ownership
        $userId = $request->getAttribute('user_id');
        $helper = $this->matchingService->getHelper($helperId);

        if (!$helper || $helper['user_id'] !== $userId) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Unauthorized'], 403);
        }

        // Validate schedule format
        // Expected: [{day: 0, start: '09:00', end: '17:00', active: true}, ...]
        if (!is_array($schedule)) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Invalid schedule format'], 400);
        }

        $this->matchingService->updateAvailability($helperId, $schedule);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Availability updated successfully'
        ]);
    }

    public function getAvailability(Request $request, Response $response, array $args): Response
    {
        $helperId = (int) $args['id'];
        $schedule = $this->matchingService->getAvailability($helperId);

        return $this->jsonResponse($response, [
            'success' => true,
            'schedule' => $schedule
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
