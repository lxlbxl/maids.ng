<?php

namespace App\Services\Agents;

use App\Models\MaidProfile;
use App\Models\User;
use App\Services\AgentService;

class GatekeeperAgent extends AgentService
{
    public function getName(): string
    {
        return 'Gatekeeper';
    }

    /**
     * Auto-verify a maid's identity using an external API (stub/mocked for now).
     */
    public function verifyIdentity(MaidProfile $maid, string $documentId, string $documentType = 'NIN'): array
    {
        $action = "verify_identity";

        try {
            // In production, call QoreID API here.
            // For now, we simulate an API call response.
            $confidence = rand(70, 100);
            
            // Artificial fail condition for testing: NIN starting with 000
            if (str_starts_with($documentId, '000')) {
                $this->escalate(
                    $action,
                    "rejected",
                    "Document flagged as invalid format by external provider.",
                    $maid,
                    10
                );
                return ['success' => false, 'status' => 'rejected', 'reason' => 'Invalid document format.'];
            }

            if ($confidence >= 90) {
                // Auto Approve
                $maid->nin_verified = true;
                $maid->save();

                $this->logDecision(
                    action: $action,
                    decision: "approved",
                    confidenceScore: $confidence,
                    reasoning: "High confidence match from external Verification API.",
                    subject: $maid
                );

                return ['success' => true, 'status' => 'approved'];
            } else {
                // Needs manual review
                $this->escalate(
                    $action,
                    "queued_for_review",
                    "External API returned low confidence score ({$confidence}%). Manual check required.",
                    $maid,
                    $confidence
                );

                return ['success' => false, 'status' => 'pending', 'reason' => 'Requiring manual review by Admin.'];
            }

        } catch (\Exception $e) {
            $this->escalate(
                $action,
                "error",
                "Verification failed due to system error: " . $e->getMessage(),
                $maid,
                0
            );

            return ['success' => false, 'status' => 'error', 'reason' => 'Verification service unavailable.'];
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
