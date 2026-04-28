<?php

namespace App\Services\Agents;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use App\Services\AgentService;

class TreasurerAgent extends AgentService
{
    public function getName(): string
    {
        return 'Treasurer';
    }

    /**
     * Calculate payout and process it. Can run nightly or weekly for completed bookings.
     */
    public function processPayout(Booking $booking): array
    {
        $action = "process_payout";

        if ($booking->status !== 'completed') {
            return ['success' => false, 'reason' => 'Booking not completed'];
        }

        if ($booking->payment_status === 'paid_out') {
            return ['success' => false, 'reason' => 'Payout already processed'];
        }

        $gross = $booking->amount; // e.g. 50000
        $commissionPercent = (float) Setting::get('commission_percent', 10);

        $commissionAmount = ($gross * $commissionPercent) / 100;
        $netPayout = $gross - $commissionAmount;

        $confidence = 100;

        // Sanity Check: Is target payout extraordinarily large?
        if ($netPayout > 200000) {
            $confidence = 30; // Suspiciously large
        }

        if ($confidence >= 90) {
            // Initiate real bank transfer via Paystack/Flutterwave API
            // ... API logic here ...

            // Mark paid locally
            $booking->update(['payment_status' => 'paid_out']);

            $this->logDecision(
                action: $action,
                decision: "transferred_funds",
                confidenceScore: $confidence,
                reasoning: "Processed standard payout of ₦" . number_format($netPayout) . " (Gross: ₦{$gross}, Commission: ₦{$commissionAmount})",
                subject: $booking
            );

            return [
                'success' => true,
                'status' => 'paid_out',
                'gross' => $gross,
                'net' => $netPayout,
                'commission' => $commissionAmount
            ];
        } else {
            // Escalate high value transfers for manual review
            $this->escalate(
                $action,
                "queued_for_approval",
                "Payout of ₦{$netPayout} exceeds auto-transfer limits. Manual approval required.",
                $booking,
                $confidence
            );

            return ['success' => false, 'status' => 'pending_approval'];
        }
    }
}
