<?php

namespace App\Jobs;

use App\Models\StandaloneVerification;
use App\Services\Agents\GatekeeperAgent;
use App\Mail\VerificationReportMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessStandaloneVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $verificationId;

    public function __construct(int $verificationId)
    {
        $this->verificationId = $verificationId;
    }

    public function handle(GatekeeperAgent $gatekeeper): void
    {
        $verification = StandaloneVerification::find($this->verificationId);

        if (!$verification) {
            Log::error("ProcessStandaloneVerification: Verification #{$this->verificationId} not found.");
            return;
        }

        if ($verification->payment_status !== 'paid') {
            Log::warning("ProcessStandaloneVerification: Verification #{$this->verificationId} payment not confirmed.");
            return;
        }

        if (in_array($verification->verification_status, ['success', 'failed'])) {
            Log::info("ProcessStandaloneVerification: Verification #{$this->verificationId} already resolved.");
            return;
        }

        $verification->update([
            'verification_status' => 'processing',
            'verification_status_detail' => 'processing',
            'verification_attempts' => ($verification->verification_attempts ?? 0) + 1,
        ]);

        $optionalFields = [];
        if ($verification->optional_fields) {
            $decoded = is_array($verification->optional_fields)
                ? $verification->optional_fields
                : json_decode($verification->optional_fields, true);
            if (is_array($decoded)) {
                $optionalFields = $decoded;
            }
        }

        Log::info("ProcessStandaloneVerification: Running QoreID NIN Premium for VRF ref {$verification->payment_reference}, attempt {$verification->verification_attempts}");

        $result = $gatekeeper->verifyNinStandalone(
            $verification->maid_nin,
            $verification->maid_first_name,
            $verification->maid_last_name,
            $optionalFields
        );

        $status = 'failed';
        $statusDetail = 'verification_failed';

        if ($result['success']) {
            $status = 'success';
            $statusDetail = 'verification_success';
        } elseif (!empty($result['is_name_mismatch'])) {
            $status = 'failed';
            $statusDetail = 'name_mismatch';
        } elseif (!empty($result['is_service_unavailable'])) {
            $status = 'service_unavailable';
            $statusDetail = 'service_unavailable';
        } elseif (!empty($result['is_insufficient_balance'])) {
            $status = 'service_unavailable';
            $statusDetail = 'insufficient_balance';
        } elseif (!empty($result['is_invalid_credentials'])) {
            $status = 'service_unavailable';
            $statusDetail = 'invalid_credentials';
        } elseif (!empty($result['is_product_denied'])) {
            $status = 'service_unavailable';
            $statusDetail = 'service_unavailable';
        }

        $verification->update([
            'verification_status' => $status,
            'verification_status_detail' => $statusDetail,
            'verification_data' => $result['data'] ?? null,
            'confidence_score' => $result['is_name_mismatch'] ? 0 : 100,
            'name_matched' => !($result['is_name_mismatch'] ?? false),
            'last_api_status_code' => (string) ($result['status_code'] ?? ''),
            'last_api_error' => $result['error'] ?? null,
            'qoreid_product_available' => $result['product_available'] ?? null,
            'external_reference' => ($result['data']['qoreid_data']['id'] ?? null)
                ? 'QOREID-' . $result['data']['qoreid_data']['id']
                : null,
        ]);

        if ($status === 'service_unavailable') {
            Log::warning("ProcessStandaloneVerification: QoreID service unavailable for ref {$verification->payment_reference}", [
                'status_code' => $result['status_code'] ?? 'unknown',
                'error' => $result['error'] ?? 'unknown',
                'detail' => $statusDetail,
            ]);
            // Do not send email for service_unavailable — will send when retried
        } else {
            $emailToSend = $verification->requester_email ?? $verification->requester?->email;
            if ($emailToSend) {
                try {
                    Mail::to($emailToSend)->send(new VerificationReportMail($verification));
                } catch (\Exception $e) {
                    Log::error("ProcessStandaloneVerification: Failed to send verification report email: " . $e->getMessage());
                }
            }
        }

        Log::info("ProcessStandaloneVerification: Completed for ref {$verification->payment_reference} with status {$status} ({$statusDetail})");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessStandaloneVerification job failed for verification #{$this->verificationId}: " . $exception->getMessage(), [
            'trace' => $exception->getTraceAsString(),
        ]);

        $verification = StandaloneVerification::find($this->verificationId);
        if ($verification && $verification->verification_status === 'processing') {
            $verification->update([
                'verification_status' => 'service_unavailable',
                'verification_status_detail' => 'service_unavailable',
                'last_api_error' => 'Job execution failed: ' . $exception->getMessage(),
            ]);
        }
    }
}
