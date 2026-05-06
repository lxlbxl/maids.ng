<?php

namespace App\Http\Controllers\Api;

use App\Models\StandaloneVerification;
use App\Models\User;
use App\Services\Agents\GatekeeperAgent;
use App\Services\QoreIDService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VerificationController extends \App\Http\Controllers\Controller
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
    public function verifyNin(Request $request)
    {
        $validated = $request->validate([
            'nin' => 'required|string|size:11|regex:/^[0-9]{11}$/',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'dob' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female,m,f',
        ]);

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
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Verification failed.',
                'status_code' => $result['status_code'] ?? 500,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $result['data']['status'] ?? 'verified',
                'confidence' => $result['confidence'] ?? 0,
                'name_match' => $result['name_match'] ?? false,
                'name_details' => $result['data']['name_details'] ?? [],
                'verified_at' => $result['data']['verified_at'] ?? now()->toIso8601String(),
                'qoreid_data' => $result['data']['qoreid_data'] ?? null,
                'normalized_data' => $result['data']['data'] ?? null,
            ],
        ]);
    }

    /**
     * GET /api/v1/verification/nin/{reference}
     * Get verification status by reference.
     */
    public function getStatus(string $reference)
    {
        $verification = StandaloneVerification::where('payment_reference', $reference)
            ->with('requester')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
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
            ],
        ]);
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
    public function batchVerify(Request $request)
    {
        $validated = $request->validate([
            'verifications' => 'required|array|max:10',
            'verifications.*.nin' => 'required|string|size:11|regex:/^[0-9]{11}$/',
            'verifications.*.first_name' => 'required|string|max:255',
            'verifications.*.last_name' => 'required|string|max:255',
            'verifications.*.middle_name' => 'nullable|string|max:255',
            'verifications.*.dob' => 'nullable|date|before:today',
            'verifications.*.phone' => 'nullable|string|max:20',
            'verifications.*.email' => 'nullable|email|max:255',
            'verifications.*.gender' => 'nullable|in:male,female,m,f',
        ]);

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
                'confidence' => $result['confidence'] ?? 0,
                'name_match' => $result['name_match'] ?? false,
                'error' => $result['error'] ?? null,
                'data' => $result['data'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($results),
                'verified' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                'results' => $results,
            ],
        ]);
    }
}