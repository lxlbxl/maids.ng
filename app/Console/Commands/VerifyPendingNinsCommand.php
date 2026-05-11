<?php

namespace App\Console\Commands;

use App\Models\NinVerification;
use App\Services\Agents\GatekeeperAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyPendingNinsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:verify-pending-nins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Picks up pending NIN verifications and processes them using the Gatekeeper Agent';

    /**
     * Execute the console command.
     */
    public function handle(GatekeeperAgent $gatekeeper)
    {
        $this->info("Starting background NIN verification sweep...");
        
        // Fetch pending verifications that haven't been processed
        $pending = NinVerification::where('status', 'pending')
            ->whereNotNull('user_id')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pending->isEmpty()) {
            $this->info("No pending verifications found.");
            return 0;
        }

        $this->info("Found {$pending->count()} pending verifications.");

        foreach ($pending as $verification) {
            $user = $verification->user;
            if (!$user) {
                $verification->update([
                    'status' => 'failed', 
                    'review_notes' => 'User not found during background sweep.',
                    'reviewed_at' => now()
                ]);
                continue;
            }

            $profile = $user->maidProfile;
            if (!$profile || !$profile->nin) {
                $verification->update([
                    'status' => 'failed', 
                    'review_notes' => 'Maid profile or NIN missing during background sweep.',
                    'reviewed_at' => now()
                ]);
                continue;
            }

            // Skip if already verified on profile
            if ($profile->nin_verified) {
                $verification->update([
                    'status' => 'verified',
                    'reviewed_at' => now()
                ]);
                $this->info("User #{$user->id} already verified on profile. Updated tracking record.");
                continue;
            }

            $this->info("Processing verification for User #{$user->id} ({$user->name})...");

            try {
                // verifyIdentity now automatically updates the NinVerification record 
                // because we updated the GatekeeperAgent logic.
                $result = $gatekeeper->verifyIdentity($profile, $profile->nin);
                
                if ($result['success']) {
                    $this->info("✓ Successfully verified #{$user->id}");
                } else {
                    $status = $result['status'] ?? 'pending';
                    $this->warn("! Status for #{$user->id}: {$status}. Reason: " . ($result['reason'] ?? 'None'));
                }
            } catch (\Exception $e) {
                $this->error("✘ Error processing #{$user->id}: " . $e->getMessage());
                Log::error("VerifyPendingNinsCommand error for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Sweep complete.");
        return 0;
    }
}
