<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Verification\{VerifyNinRequest, BatchVerifyNinRequest};
use App\Models\StandaloneVerification;
use App\Models\User;
use App\Services\Agents\GatekeeperAgent;
use App\Services\QoreIDService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VerificationController extends ApiController
{
    protected QoreIDService $qoreid;
    protected GatekeeperAgent $gatekeeper;

    public function __construct()
    {
        $this->qoreid = new QoreIDService();
        $this->gatekeeper = new GatekeeperAgent();
    }

    /**
     * POST /api/v1/verification/nin
     * Verify a NIN using QoreID NIN Premium API.
     *
     * @bodyParam nin string required 11-digit National Identity Number. Example: 63184876213
     * @bodyParam first_name string required First name. Example: BUNCH
     * @bodyParam last_name string required Last name. Example: DILLON
     * @bodyParam middle_name string optional Middle name.
     * @bodyParam dob string optional Date of birth (YYYY-MM-DD).
     * @bodyParam phone string optional Phone number.
     * @bodyParam email string optional Email address.
     * @bodyParam gender string optional Gender (m/f).
     *
     * @response {
     *   "success": true,
     *   "data": {
     *     "verification_id": 1,
     *     "status": "verified",
     *     "confidence": 100,
     *     "name_match": true,
     *     "qoreid_data": { ... }
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Validation error",
     *   "errors": { ... }
     * }
     */
    public function verifyNin(VerifyNinRequest $request): JsonResponse
    {
        $validated = $request->validated();
 
        // Build optional fields
        $optionalFields = [];
        if (!empty($validated['middle_name'])) {
            $optionalFields['middlename'] = $validated['middle_name'];
        }
        if (!empty($validated['dob'])) {
            $optionalFields['dob'] = date('Y-m-d', strtotime($validated['dob']));
        }
        if (!empty($validated['phone'])) {
            $optionalFields['phone'] = $validated['phone'];
        }
        if (!empty($validated['email'])) {
            $optionalFields['email'] = $validated['email'];
        }
        if (!empty($validated['gender'])) {
            $gender = in_array(strtolower($validated['gender']), ['m', 'male']) ? 'm' : 'f';
            $optionalFields['gender'] = $gender;
        }

        // Call QoreID via Gatekeeper
        $result = $this->gatekeeper->verifyNinStandalone(
            $validated['nin'],
            $validated['first_name'],
            $validated['last_name'],
            $optionalFields
        );

        if (!$result['success']) {
            if (!empty($result['is_name_mismatch'])) {
                return $this->error(
                    'Name mismatch. The NIN is valid but the name on record does not match the provided name. Please check your full name and try again.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    [
                        'status' => 'mismatch',
                        'qoreid_data' => $result['data']['qoreid_data'] ?? null,
                    ]
                );
            }
            return $this->error($result['error'] ?? 'Verification failed.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->success([
            'status' => 'verified',
            'qoreid_data' => $result['data']['qoreid_data'] ?? null,
        ], 'NIN verification successful');
    }

    /**
     * GET /api/v1/verification/nin/{reference}
     * Get verification status by reference.
     */
    public function getStatus(string $reference): JsonResponse
    {
        $verification = StandaloneVerification::where('payment_reference', $reference)
            ->with('requester')
            ->firstOrFail();
 
        return $this->success([
            'id' => $verification->id,
            'reference' => $verification->payment_reference,
            'nin' => $verification->maid_nin,
            'first_name' => $verification->maid_first_name,
            'last_name' => $verification->maid_last_name,
            'payment_status' => $verification->payment_status,
            'verification_status' => $verification->verification_status,
            'confidence_score' => $verification->confidence_score,
            'name_matched' => $verification->name_matched,
            'external_reference' => $verification->external_reference,
            'verification_data' => $verification->verification_data,
            'qoreid_data' => $verification->qore_id_data,
            'normalized_data' => $verification->normalized_data,
            'is_verified' => $verification->is_verified,
            'created_at' => $verification->created_at->toIso8601String(),
            'updated_at' => $verification->updated_at->toIso8601String(),
        ], 'Verification status retrieved successfully');
    }

    /**
     * POST /api/v1/verification/nin/batch
     * Batch verify multiple NINs.
     *
     * @bodyParam verifications array required Array of verification requests.
     * @bodyParam verifications.*.nin string required
     * @bodyParam verifications.*.first_name string required
     * @bodyParam verifications.*.last_name string required
     * @bodyParam verifications.*.middle_name string optional
     * @bodyParam verifications.*.dob string optional
     * @bodyParam verifications.*.phone string optional
     * @bodyParam verifications.*.email string optional
     * @bodyParam verifications.*.gender string optional
     */
    public function batchVerify(BatchVerifyNinRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = [];

        foreach ($validated['verifications'] as $index => $item) {
            $optionalFields = [];
            if (!empty($item['middle_name'])) {
                $optionalFields['middlename'] = $item['middle_name'];
            }
            if (!empty($item['dob'])) {
                $optionalFields['dob'] = date('Y-m-d', strtotime($item['dob']));
            }
            if (!empty($item['phone'])) {
                $optionalFields['phone'] = $item['phone'];
            }
            if (!empty($item['email'])) {
                $optionalFields['email'] = $item['email'];
            }
            if (!empty($item['gender'])) {
                $gender = in_array(strtolower($item['gender']), ['m', 'male']) ? 'm' : 'f';
                $optionalFields['gender'] = $gender;
            }

            $result = $this->gatekeeper->verifyNinStandalone(
                $item['nin'],
                $item['first_name'],
                $item['last_name'],
                $optionalFields
            );

            $results[] = [
                'index' => $index,
                'nin' => $item['nin'],
                'success' => $result['success'],
                'status' => $result['status'] ?? 'failed',
                'error' => $result['error'] ?? null,
                'is_name_mismatch' => $result['is_name_mismatch'] ?? false,
                'data' => $result['data'] ?? null,
            ];
        }

        return $this->success([
            'total' => count($results),
            'verified' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results,
        ], 'Batch verification processed');
    }
}