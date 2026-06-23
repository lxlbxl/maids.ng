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
     * Returns one of three outcomes: success, mismatch, failed.
     */
    public function verifyIdentity(MaidProfile $maid, string $documentId, string $documentType = 'NIN'): array
    {
        $action = "verify_identity";

        try {
            $firstName = $maid->first_name ?: (explode(' ', $maid->user->name)[0] ?? '');
            $lastName = $maid->last_name ?: (explode(' ', $maid->user->name)[1] ?? '');

            $apiResult = $this->qoreid->verifyNinPremium(
                $documentId, $firstName, $lastName,
                ['dob' => $maid->dob ?? '', 'phone' => $maid->user->phone ?? '']
            );

            // Outcome 1: API failed (NIN not found, network error, etc.)
            if (!$apiResult['success']) {
                $this->updateNinTrackingRecord($maid->user_id, 'failed');
                $this->escalate($action, "rejected", $apiResult['error'] ?? 'QoreID API error', $maid, 0);
                return ['success' => false, 'status' => 'failed', 'reason' => $apiResult['error'] ?? 'Verification failed.'];
            }

            $qoreData = $apiResult['data'] ?? [];

            // Try both name orders
            $nameMatch = $this->compareNamesBestMatch($firstName, $lastName, $qoreData);

            // Outcome 2: Success — names match
            if ($nameMatch) {
                $maid->nin_verified = true;
                $maid->nin_report = json_encode(['verified_at' => now()->toDateTimeString(), 'method' => 'QoreID NIN Premium', 'qoreid_response' => $qoreData]);
                $maid->save();

                $this->updateNinTrackingRecord($maid->user_id, 'verified', 100, null, $qoreData);
                $this->sendVerificationNotification($maid->user, 'approved');

                $this->logDecision(action: $action, decision: "approved", confidenceScore: 100,
                    reasoning: "QoreID NIN Premium passed. Names matched.", subject: $maid);

                return ['success' => true, 'status' => 'success', 'data' => ['qoreid_response' => $qoreData]];
            }

            // Outcome 3: Mismatch — NIN is valid but names don't match
            $this->updateNinTrackingRecord($maid->user_id, 'review_required', 0, null, $qoreData);
            $this->sendVerificationNotification($maid->user, 'mismatch');

            $this->escalate($action, "queued_for_review",
                "{$maid->user->name} (NIN: {$documentId}): Names do not match NIN record. Full QoreID payload stored for admin review.", $maid, 0);

            return ['success' => false, 'status' => 'mismatch',
                'reason' => 'Names on NIN do not match. Please ensure your full name matches your NIN slip.'];

        } catch (\Exception $e) {
            Log::error('GatekeeperAgent verifyIdentity error', ['maid_id' => $maid->id, 'error' => $e->getMessage()]);
            $this->escalate($action, "error", "{$maid->user->name} (NIN: {$documentId}): System error: " . $e->getMessage(), $maid, 0);
            $this->updateNinTrackingRecord($maid->user_id, 'failed');
            return ['success' => false, 'status' => 'failed', 'reason' => 'Verification service unavailable.'];
        }
    }

    protected function compareNamesBestMatch(string $firstName, string $lastName, array $qoreData): bool
    {
        $original = $this->qoreid->compareNames($firstName, $lastName, $qoreData);
        if ($original['match']) return true;

        $swapped = $this->qoreid->compareNames($lastName, $firstName, $qoreData);
        if ($swapped['match']) {
            Log::info('GatekeeperAgent: Name swap produced match');
            return true;
        }

        return false;
    }

    protected function sendVerificationNotification(User $user, string $status): void
    {
        try {
            $isApproved = $status === 'approved';
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'type' => 'verification',
                'title' => $isApproved ? 'NIN Verification Approved' : 'NIN Verification Needs Attention',
                'message' => $isApproved
                    ? 'Your NIN has been verified. Your profile is now active.'
                    : 'Your NIN could not be verified because the names do not match. Please ensure your full name matches exactly as it appears on your NIN slip, then log in and update your details.',
                'data' => ['status' => $status],
            ]);

            if (!empty($user->phone)) {
                try {
                    $sms = app(\App\Services\NotificationService::class);
                    $sms->sendTemplate($user, $isApproved ? 'verification_complete' : 'verification_failed', ['name' => $user->name], 'verification');
                } catch (\Exception $e) {
                    Log::warning('GatekeeperAgent: SMS failed for user ' . $user->id);
                }
            }
        } catch (\Exception $e) {
            Log::warning('GatekeeperAgent: Notification failed for user ' . $user->id);
        }
    }

    protected function updateNinTrackingRecord(int $userId, string $status, int $confidence = 0, ?string $reference = null, ?array $qoreidPayload = null): void
    {
        try {
            $update = ['status' => $status, 'confidence_score' => $confidence, 'external_reference' => $reference, 'reviewed_at' => ($status !== 'pending') ? now() : null];
            if ($qoreidPayload) $update['qoreid_payload'] = $qoreidPayload;
            \App\Models\NinVerification::where('user_id', $userId)->where('status', 'pending')->latest()->update($update);
        } catch (\Exception $e) {
            Log::warning("Failed to update NinVerification for user {$userId}: " . $e->getMessage());
        }
    }

    public function verifyNinStandalone(string $nin, string $firstName, string $lastName, array $optional = []): array
    {
        $action = "standalone_verification";

        try {
            $apiResult = $this->qoreid->verifyNinPremium($nin, $firstName, $lastName, $optional);

            if (!$apiResult['success']) {
                $statusCode = $apiResult['status_code'] ?? 0;
                $result = ['success' => false, 'data' => null, 'error' => $apiResult['error'], 'status_code' => $statusCode, 'status' => 'failed'];

                if ($statusCode === 404) $result['is_product_denied'] = true;
                elseif ($statusCode === 402) $result['is_insufficient_balance'] = true;
                elseif (in_array($statusCode, [401, 403])) $result['is_invalid_credentials'] = true;
                elseif (in_array($statusCode, [500, 502, 503, 0])) $result['is_service_unavailable'] = true;

                $this->logDecision(action: $action, decision: "failed", confidenceScore: 0,
                    reasoning: "QoreID API error for NIN {$nin} (HTTP {$statusCode})", subject: null);

                return $result;
            }

            $qoreData = $apiResult['data'] ?? [];
            $nameMatch = $this->compareNamesBestMatch($firstName, $lastName, $qoreData);

            if ($nameMatch) {
                $this->logDecision(action: $action, decision: "success", confidenceScore: 100,
                    reasoning: "Standalone QoreID NIN Premium passed for NIN: {$nin}. Names matched.", subject: null);

                return ['success' => true, 'data' => ['status' => 'verified', 'qoreid_data' => $qoreData], 'status_code' => 200, 'product_available' => true, 'status' => 'success'];
            }

            $this->logDecision(action: $action, decision: "failed", confidenceScore: 0,
                reasoning: "Standalone QoreID NIN Premium mismatch for NIN: {$nin}. Names do not match.", subject: null);

            return ['success' => false, 'data' => ['status' => 'name_mismatch', 'qoreid_data' => $qoreData], 'is_name_mismatch' => true, 'status_code' => 200, 'product_available' => true, 'status' => 'mismatch'];

        } catch (\Exception $e) {
            Log::error('GatekeeperAgent verifyNinStandalone error', ['nin' => $nin, 'error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'error' => $e->getMessage(), 'is_service_unavailable' => true, 'status_code' => 500, 'status' => 'failed'];
        }
    }

    public function recordManualApproval(MaidProfile $maid, User $admin): void
    {
        $this->logDecision(action: "manual_verify_identity", decision: "approved", confidenceScore: 100,
            reasoning: "Manual verification by Admin: {$admin->name}.", subject: $maid);
    }
}
