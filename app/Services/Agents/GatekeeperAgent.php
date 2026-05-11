<?php

namespace App\Services\Agents;

use App\Models\MaidProfile;
use App\Models\User;
use App\Services\AgentService;
use App\Services\QoreIDService;
use Illuminate\Support\Facades\Log;

class GatekeeperAgent extends AgentService
{
    protected QoreIDService $qoreid;
    protected string $agentName = 'gatekeeper';

    public function __construct()
    {
        parent::__construct();
        $this->qoreid = new QoreIDService();
    }

    public function getName(): string
    {
        return 'Gatekeeper';
    }

    /**
     * Auto-verify a maid's identity using QoreID NIN Premium API.
     */
    public function verifyIdentity(MaidProfile $maid, string $documentId, string $documentType = 'NIN'): array
    {
        $action = "verify_identity";

        try {
            // Get user name parts
            $nameParts = explode(' ', $maid->user->name);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            // Call QoreID NIN Premium API
            $apiResult = $this->qoreid->verifyNinPremium(
                $documentId,
                $firstName,
                $lastName,
                [
                    'dob' => $maid->dob ?? '',
                    'phone' => $maid->user->phone ?? '',
                ]
            );

            if (!$apiResult['success']) {
                $this->updateNinTrackingRecord($maid->user_id, 'failed', 0);
                $this->escalate(
                    $action,
                    "rejected",
                    "QoreID API error: " . ($apiResult['error'] ?? 'Unknown error'),
                    $maid,
                    0
                );
                return [
                    'success' => false,
                    'status' => 'rejected',
                    'reason' => $apiResult['error'] ?? 'Verification failed.',
                ];
            }

            $qoreData = $apiResult['data'] ?? [];
            $identityData = $qoreData['nin'] ?? $qoreData['nin_premium'] ?? $qoreData['data'] ?? $qoreData;

            // Compare names
            $nameComparison = $this->qoreid->compareNames($firstName, $lastName, $qoreData);
            $confidence = $nameComparison['confidence'];

            // Build verification report — include all QoreID NIN Premium fields
            $report = [
                'verified_at' => now()->toDateTimeString(),
                'method' => 'QoreID NIN Premium',
                'confidence_score' => $confidence,
                'name_match' => $nameComparison['match'],
                'name_details' => $nameComparison['details'],
                'document_type' => 'NIN',
                'verification_reference' => 'VR-' . strtoupper(uniqid()),
                'qoreid_response' => $qoreData,
                'details' => [
                    'first_name' => $identityData['firstname'] ?? $identityData['firstName'] ?? $firstName,
                    'last_name' => $identityData['lastname'] ?? $identityData['lastName'] ?? $lastName,
                    'middlename' => $identityData['middlename'] ?? $identityData['middleName'] ?? '',
                    'title' => $identityData['title'] ?? '',
                    'dob' => $identityData['birthdate'] ?? $identityData['birthDate'] ?? '',
                    'gender' => $identityData['gender'] ?? '',
                    'phone' => $identityData['phone'] ?? '',
                    'email' => $identityData['email'] ?? '',
                    'height' => $identityData['height'] ?? '',
                    'profession' => $identityData['profession'] ?? '',
                    'marital_status' => $identityData['maritalStatus'] ?? '',
                    'employment_status' => $identityData['employmentStatus'] ?? '',
                    'birth_state' => $identityData['birthState'] ?? '',
                    'birth_country' => $identityData['birthCountry'] ?? '',
                    'religion' => $identityData['religion'] ?? '',
                    'nationality' => $identityData['nationality'] ?? '',
                    'lga_of_origin' => $identityData['lgaOfOrigin'] ?? '',
                    'state_of_origin' => $identityData['stateOfOrigin'] ?? '',
                    'nspokenlang' => $identityData['nspokenlang'] ?? '',
                    'ospokenlang' => $identityData['ospokenlang'] ?? '',
                    'parent_lastname' => $identityData['parentLastname'] ?? $identityData['parentLastName'] ?? '',
                    'photo' => $identityData['photo'] ?? '',
                    'residence' => $identityData['residence'] ?? null,
                    'next_of_kin' => $identityData['nextOfKin'] ?? null,
                    'insight' => $identityData['insight'] ?? $qoreData['insight'] ?? null,
                ],
            ];

            if ($confidence >= 80) {
                // Auto Approve
                $maid->nin_verified = true;
                $maid->nin_report = json_encode($report);
                $maid->save();

                $this->updateNinTrackingRecord($maid->user_id, 'verified', $confidence, $report['verification_reference']);

                $this->logDecision(
                    action: $action,
                    decision: "approved",
                    confidenceScore: $confidence,
                    reasoning: "QoreID NIN Premium verification passed with {$confidence}% confidence. Name match: " .
                    ($nameComparison['match'] ? 'Yes' : 'No'),
                    subject: $maid
                );

                return [
                    'success' => true,
                    'status' => 'approved',
                    'confidence' => $confidence,
                    'data' => $report,
                ];
            } else {
                // Needs manual review
                $this->updateNinTrackingRecord($maid->user_id, 'review_required', $confidence, $report['verification_reference']);

                $this->escalate(
                    $action,
                    "queued_for_review",
                    "QoreID returned low confidence score ({$confidence}%). Name match: " .
                    ($nameComparison['match'] ? 'Yes' : 'No') . ". Manual check required.",
                    $maid,
                    $confidence
                );

                return [
                    'success' => false,
                    'status' => 'pending',
                    'reason' => "Low confidence score ({$confidence}%). Requiring manual review by Admin.",
                    'confidence' => $confidence,
                    'data' => $report,
                ];
            }

        } catch (\Exception $e) {
            Log::error('GatekeeperAgent verifyIdentity error', [
                'maid_id' => $maid->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->escalate(
                $action,
                "error",
                "Verification failed due to system error: " . $e->getMessage(),
                $maid,
                0
            );

            $this->updateNinTrackingRecord($maid->user_id, 'failed', 0);
            return [
                'success' => false,
                'status' => 'error',
                'reason' => 'Verification service unavailable. Please try again later.',
            ];
        }
    }

    /**
     * Helper to update the NinVerification tracking record.
     */
    protected function updateNinTrackingRecord(int $userId, string $status, int $confidence = 0, ?string $reference = null): void
    {
        try {
            \App\Models\NinVerification::where('user_id', $userId)
                ->where('status', 'pending')
                ->latest()
                ->update([
                    'status' => $status,
                    'confidence_score' => $confidence,
                    'external_reference' => $reference,
                    'reviewed_at' => ($status !== 'pending') ? now() : null,
                ]);
        } catch (\Exception $e) {
            Log::warning("Failed to update NinVerification record for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Standalone NIN verification without profile requirement.
     *
     * @param string $nin       11-digit NIN
     * @param string $firstName First name
     * @param string $lastName  Last name
     * @param array  $optional  Optional fields: middlename, dob, phone, email, gender
     */
    public function verifyNinStandalone(string $nin, string $firstName, string $lastName, array $optional = []): array
    {
        $action = "standalone_verification";

        try {
            $apiResult = $this->qoreid->verifyNinPremium($nin, $firstName, $lastName, $optional);

            if (!$apiResult['success']) {
                $statusCode = $apiResult['status_code'] ?? 0;
                $errorMsg = $apiResult['error'] ?? 'Verification failed.';

                $result = [
                    'success' => false,
                    'data' => null,
                    'error' => $errorMsg,
                    'status_code' => $statusCode,
                    'confidence' => 0,
                    'name_match' => false,
                ];

                if ($statusCode === 404) {
                    $result['is_product_denied'] = true;
                    $result['product_available'] = false;
                    $result['error'] = 'QoreID NIN Premium is not available for this account. Please check your product subscription.';
                } elseif ($statusCode === 402) {
                    $result['is_insufficient_balance'] = true;
                    $result['product_available'] = true;
                    $result['error'] = 'QoreID account has insufficient credits. Please top up your balance.';
                } elseif (in_array($statusCode, [401, 403])) {
                    $result['is_invalid_credentials'] = true;
                    $result['product_available'] = false;
                    $result['error'] = 'QoreID authentication or access issue. Please verify your API credentials and product subscription.';
                } elseif (in_array($statusCode, [500, 502, 503, 0])) {
                    $result['is_service_unavailable'] = true;
                    $result['product_available'] = null;
                    $result['error'] = 'QoreID service is temporarily unavailable. Please try again later.';
                }

                $this->logDecision(
                    action: $action,
                    decision: "failed",
                    confidenceScore: 0,
                    reasoning: "QoreID API error for NIN {$nin} (HTTP {$statusCode}): {$errorMsg}",
                    subject: null
                );

                return $result;
            }

            $qoreData = $apiResult['data'] ?? [];
            $identityData = $qoreData['nin'] ?? $qoreData['nin_premium'] ?? $qoreData['data'] ?? $qoreData;

            $nameComparison = $this->qoreid->compareNames($firstName, $lastName, $qoreData);
            $confidence = $nameComparison['confidence'];
            $isMatch = $nameComparison['match'];

            $verificationData = [
                'status' => ($isMatch && $confidence >= 80) ? 'verified' : 'failed',
                'confidence' => $confidence,
                'name_match' => $isMatch,
                'name_details' => $nameComparison['details'],
                'verified_at' => now()->toDateTimeString(),
                'qoreid_data' => $qoreData,
                'data' => [
                    'nin' => $identityData['nin'] ?? $nin,
                    'first_name' => $identityData['firstname'] ?? $identityData['firstName'] ?? $firstName,
                    'last_name' => $identityData['lastname'] ?? $identityData['lastName'] ?? $lastName,
                    'middlename' => $identityData['middlename'] ?? $identityData['middleName'] ?? '',
                    'title' => $identityData['title'] ?? '',
                    'dob' => $identityData['birthdate'] ?? $identityData['birthDate'] ?? '',
                    'gender' => $identityData['gender'] ?? '',
                    'phone' => $identityData['phone'] ?? '',
                    'email' => $identityData['email'] ?? '',
                    'height' => $identityData['height'] ?? '',
                    'profession' => $identityData['profession'] ?? '',
                    'marital_status' => $identityData['maritalStatus'] ?? '',
                    'employment_status' => $identityData['employmentStatus'] ?? '',
                    'birth_state' => $identityData['birthState'] ?? '',
                    'birth_country' => $identityData['birthCountry'] ?? '',
                    'religion' => $identityData['religion'] ?? '',
                    'nationality' => $identityData['nationality'] ?? '',
                    'lga_of_origin' => $identityData['lgaOfOrigin'] ?? '',
                    'state_of_origin' => $identityData['stateOfOrigin'] ?? '',
                    'nspokenlang' => $identityData['nspokenlang'] ?? '',
                    'ospokenlang' => $identityData['ospokenlang'] ?? '',
                    'parent_lastname' => $identityData['parentLastname'] ?? $identityData['parentLastName'] ?? '',
                    'photo' => $identityData['photo'] ?? '',
                    'residence' => $identityData['residence'] ?? null,
                    'next_of_kin' => $identityData['nextOfKin'] ?? null,
                    'insight' => $identityData['insight'] ?? $qoreData['insight'] ?? null,
                ],
            ];

            $this->logDecision(
                action: $action,
                decision: ($isMatch && $confidence >= 80) ? "success" : "failed",
                confidenceScore: $confidence,
                reasoning: "Standalone QoreID NIN Premium verification for NIN: {$nin}. " .
                "Confidence: {$confidence}%, Name match: " . ($isMatch ? 'Yes' : 'No'),
                subject: null
            );

            $result = [
                'success' => ($isMatch && $confidence >= 80),
                'data' => $verificationData,
                'confidence' => $confidence,
                'name_match' => $isMatch,
                'status_code' => $apiResult['status_code'] ?? 200,
                'product_available' => true,
            ];

            if (!$isMatch || $confidence < 80) {
                $result['is_name_mismatch'] = true;
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('GatekeeperAgent verifyNinStandalone error', [
                'nin' => $nin,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
                'is_service_unavailable' => true,
                'status_code' => 500,
            ];
        }
    }

    /**
     * Record a manual verification made by an administrator.
     */
    public function recordManualApproval(MaidProfile $maid, User $admin): void
    {
        $this->logDecision(
            action: "manual_verify_identity",
            decision: "approved",
            confidenceScore: 100,
            reasoning: "Manual verification performed by Admin: {$admin->name}.",
            subject: $maid
        );
    }
}
