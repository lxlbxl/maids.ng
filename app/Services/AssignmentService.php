<?php

namespace App\Services;

use App\Events\AssignmentRejected;
use App\Models\EmployerPreference;
use App\Models\MaidAssignment;
use App\Models\User;
use App\Services\WalletService;
use App\Services\SmartNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignmentService
{
    protected WalletService $walletService;
    protected SmartNotificationService $notificationService;

    public function __construct(
        WalletService $walletService,
        SmartNotificationService $notificationService
    ) {
        $this->walletService = $walletService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a direct selection assignment (employer selects maid from search).
     * This is auto-accepted immediately.
     */
    public function createDirectSelectionAssignment(
        int $employerId,
        int $maidId,
        ?int $preferenceId = null,
        array $additionalData = []
    ): ?MaidAssignment {
        try {
            DB::beginTransaction();

            // Check if maid is available
            $maid = User::find($maidId);
            if (!$maid || !$this->isMaidAvailable($maidId)) {
                DB::rollBack();
                Log::warning('Maid not available for direct selection', [
                    'maid_id' => $maidId,
                    'employer_id' => $employerId,
                ]);
                return null;
            }

            // Create the assignment
            $assignment = MaidAssignment::create([
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'preference_id' => $preferenceId,
                'assigned_by' => $employerId,
                'assignment_type' => 'direct_selection',
                'status' => 'accepted', // Auto-accepted
                'matching_fee_paid' => $additionalData['matching_fee_paid'] ?? false,
                'matching_fee_amount' => $additionalData['matching_fee_amount'] ?? 0,
                'guarantee_match' => false,
                'guarantee_period_days' => $additionalData['guarantee_period_days'] ?? 90,
                'ai_match_score' => $additionalData['ai_match_score'] ?? null,
                'ai_match_reasoning' => $additionalData['ai_match_reasoning'] ?? null,
                'employer_accepted_at' => now(),
                'started_at' => now(),
                'matched_until' => now()->addDays($additionalData['guarantee_period_days'] ?? 90),
                'salary_amount' => $additionalData['salary_amount'] ?? null,
                'salary_currency' => $additionalData['salary_currency'] ?? 'NGN',
                'job_location' => $additionalData['job_location'] ?? null,
                'job_type' => $additionalData['job_type'] ?? null,
                'special_requirements' => $additionalData['special_requirements'] ?? null,
                'notes' => $additionalData['notes'] ?? null,
            ]);

            // Update maid availability to matched
            $maid->maidProfile()->update(['availability_status' => 'matched']);

            // Send SMS notification to maid
            $this->notificationService->sendMaidAssignmentNotification($assignment);

            DB::commit();

            Log::info('Direct selection assignment created and auto-accepted', [
                'assignment_id' => $assignment->id,
                'employer_id' => $employerId,
                'maid_id' => $maidId,
            ]);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create direct selection assignment', [
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a guarantee match assignment (AI or admin assigns maid).
     * Employer must accept/reject.
     */
    public function createGuaranteeMatchAssignment(
        int $employerId,
        int $maidId,
        int $assignedBy,
        ?int $preferenceId = null,
        array $additionalData = []
    ): ?MaidAssignment {
        try {
            DB::beginTransaction();

            // Check if maid is available
            if (!$this->isMaidAvailable($maidId)) {
                DB::rollBack();
                Log::warning('Maid not available for guarantee match', [
                    'maid_id' => $maidId,
                    'employer_id' => $employerId,
                ]);
                return null;
            }

            // Determine assignment type based on who assigned
            $assigner = User::find($assignedBy);
            $assignmentType = 'manual';
            if ($assigner && $assigner->hasRole('ai_agent')) {
                $assignmentType = 'auto';
            }

            // Create the assignment with pending acceptance status
            $assignment = MaidAssignment::create([
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'preference_id' => $preferenceId,
                'assigned_by' => $assignedBy,
                'assignment_type' => $assignmentType,
                'status' => 'pending_acceptance',
                'matching_fee_paid' => true, // Already paid for guarantee match
                'matching_fee_amount' => $additionalData['matching_fee_amount'] ?? 0,
                'guarantee_match' => true,
                'guarantee_period_days' => $additionalData['guarantee_period_days'] ?? 90,
                'ai_match_score' => $additionalData['ai_match_score'] ?? null,
                'ai_match_reasoning' => $additionalData['ai_match_reasoning'] ?? null,
                'salary_amount' => $additionalData['salary_amount'] ?? null,
                'salary_currency' => $additionalData['salary_currency'] ?? 'NGN',
                'job_location' => $additionalData['job_location'] ?? null,
                'job_type' => $additionalData['job_type'] ?? null,
                'special_requirements' => $additionalData['special_requirements'] ?? null,
                'notes' => $additionalData['notes'] ?? null,
            ]);

            // Send notification to employer for acceptance
            $this->notificationService->sendEmployerAssignmentNotification($assignment);

            DB::commit();

            Log::info('Guarantee match assignment created, pending employer acceptance', [
                'assignment_id' => $assignment->id,
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'assigned_by' => $assignedBy,
            ]);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create guarantee match assignment', [
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Employer accepts a guarantee match assignment.
     */
    public function acceptAssignment(int $assignmentId, ?string $notes = null): ?MaidAssignment
    {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::find($assignmentId);

            if (!$assignment || !$assignment->isPendingAcceptance()) {
                DB::rollBack();
                return null;
            }

            // Update assignment status
            $assignment->accept();

            // Add notes if provided
            if ($notes) {
                $assignment->update(['notes' => $notes]);
            }

            // Send SMS notification to maid
            $this->notificationService->sendMaidAssignmentNotification($assignment);

            // Create salary schedule for this assignment
            $this->createSalarySchedule($assignment);

            DB::commit();

            Log::info('Assignment accepted by employer', [
                'assignment_id' => $assignmentId,
                'employer_id' => $assignment->employer_id,
                'maid_id' => $assignment->maid_id,
            ]);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept assignment', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Employer rejects a guarantee match assignment.
     */
    public function rejectAssignment(
        int $assignmentId,
        string $reason = '',
        bool $triggerReplacement = true
    ): ?MaidAssignment {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::find($assignmentId);

            if (!$assignment || !$assignment->isPendingAcceptance()) {
                DB::rollBack();
                return null;
            }

            // Process rejection (this handles refund)
            $assignment->reject($reason);

            // Send notification to admin/AI about rejection
            $this->notificationService->sendAssignmentRejectionNotification($assignment, $reason);

            DB::commit();

            // Trigger replacement search if guarantee match
            if ($triggerReplacement && $assignment->isGuaranteeMatch()) {
                event(new AssignmentRejected($assignment));
            }

            Log::info('Assignment rejected by employer', [
                'assignment_id' => $assignmentId,
                'employer_id' => $assignment->employer_id,
                'reason' => $reason,
                'trigger_replacement' => $triggerReplacement,
            ]);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject assignment', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Complete an assignment.
     */
    public function completeAssignment(int $assignmentId, ?string $notes = null): ?MaidAssignment
    {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::find($assignmentId);

            if (!$assignment || !$assignment->isActive()) {
                DB::rollBack();
                return null;
            }

            $assignment->complete();

            if ($notes) {
                $assignment->update(['notes' => $notes]);
            }

            // Send completion notifications
            $this->notificationService->sendAssignmentCompletionNotification($assignment);

            DB::commit();

            Log::info('Assignment completed', [
                'assignment_id' => $assignmentId,
            ]);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete assignment', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Cancel an assignment.
     */
    public function cancelAssignment(
        int $assignmentId,
        string $reason = '',
        ?int $cancelledBy = null
    ): ?MaidAssignment {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::find($assignmentId);

            if (!$assignment || $assignment->isCompleted() || $assignment->isCancelled()) {
                DB::rollBack();
                return null;
            }

            $assignment->cancel($reason, $cancelledBy);

            // Send cancellation notifications
            $this->notificationService->sendAssignmentCancellationNotification($assignment, $reason);

            DB::commit();

            Log::info('Assignment cancelled', [
                'assignment_id' => $assignmentId,
                'reason' => $reason,
                'cancelled_by' => $cancelledBy,
            ]);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel assignment', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find replacement maid for a rejected assignment.
     */
    public function findReplacementMaid(int $assignmentId): ?MaidAssignment
    {
        try {
            $originalAssignment = MaidAssignment::find($assignmentId);

            if (!$originalAssignment || !$originalAssignment->isRejected()) {
                return null;
            }

            // Get the preference to find similar maids
            $preference = $originalAssignment->preference;

            if (!$preference) {
                Log::warning('No preference found for replacement search', [
                    'assignment_id' => $assignmentId,
                ]);
                return null;
            }

            // Use ScoutAgent to find matching maids
            $scoutAgent = new \App\Services\ScoutAgent();
            $matches = $scoutAgent->findMatches($preference->toArray());

            // Filter out the rejected maid and already assigned maids
            $availableMatches = array_filter($matches, function ($match) use ($originalAssignment) {
                return $match['maid_id'] != $originalAssignment->maid_id
                    && $this->isMaidAvailable($match['maid_id']);
            });

            if (empty($availableMatches)) {
                Log::info('No replacement maids found', [
                    'assignment_id' => $assignmentId,
                ]);
                return null;
            }

            // Get the best match
            $bestMatch = $availableMatches[0];

            // Create new assignment with the replacement maid
            $replacementAssignment = $this->createGuaranteeMatchAssignment(
                $originalAssignment->employer_id,
                $bestMatch['maid_id'],
                $originalAssignment->assigned_by,
                $originalAssignment->preference_id,
                [
                    'matching_fee_amount' => 0, // No additional fee for replacement
                    'guarantee_period_days' => $originalAssignment->guarantee_period_days,
                    'ai_match_score' => $bestMatch['score'] ?? null,
                    'ai_match_reasoning' => $bestMatch['reasoning'] ?? null,
                    'salary_amount' => $originalAssignment->salary_amount,
                    'salary_currency' => $originalAssignment->salary_currency,
                    'job_location' => $originalAssignment->job_location,
                    'job_type' => $originalAssignment->job_type,
                    'notes' => 'Replacement for rejected assignment #' . $originalAssignment->id,
                ]
            );

            if ($replacementAssignment) {
                Log::info('Replacement maid found and assigned', [
                    'original_assignment_id' => $assignmentId,
                    'new_assignment_id' => $replacementAssignment->id,
                    'new_maid_id' => $bestMatch['maid_id'],
                ]);
            }

            return $replacementAssignment;
        } catch (\Exception $e) {
            Log::error('Failed to find replacement maid', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if a maid is available for assignment.
     */
    public function isMaidAvailable(int $maidId): bool
    {
        $maid = User::find($maidId);

        if (!$maid) {
            return false;
        }

        $profile = $maid->maidProfile;

        if (!$profile) {
            return false;
        }

        // Check if maid is available
        if ($profile->availability_status !== 'available') {
            return false;
        }

        // Check if maid has any active assignments
        $activeAssignments = MaidAssignment::forMaid($maidId)
            ->active()
            ->exists();

        return !$activeAssignments;
    }

    /**
     * Get pending acceptance assignments for an employer.
     */
    public function getPendingAcceptanceForEmployer(int $employerId): \Illuminate\Database\Eloquent\Collection
    {
        return MaidAssignment::forEmployer($employerId)
            ->pendingAcceptance()
            ->with(['maid', 'maid.maidProfile'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active assignments for an employer.
     */
    public function getActiveAssignmentsForEmployer(int $employerId): \Illuminate\Database\Eloquent\Collection
    {
        return MaidAssignment::forEmployer($employerId)
            ->active()
            ->with(['maid', 'maid.maidProfile'])
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Get active assignments for a maid.
     */
    public function getActiveAssignmentsForMaid(int $maidId): \Illuminate\Database\Eloquent\Collection
    {
        return MaidAssignment::forMaid($maidId)
            ->active()
            ->with(['employer'])
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Get assignment history for an employer.
     */
    public function getAssignmentHistoryForEmployer(int $employerId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return MaidAssignment::forEmployer($employerId)
            ->with(['maid', 'maid.maidProfile'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get assignment history for a maid.
     */
    public function getAssignmentHistoryForMaid(int $maidId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return MaidAssignment::forMaid($maidId)
            ->with(['employer'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark matching fee as paid for an assignment.
     */
    public function markMatchingFeePaid(int $assignmentId, float $amount): ?MaidAssignment
    {
        try {
            $assignment = MaidAssignment::find($assignmentId);

            if (!$assignment) {
                return null;
            }

            $assignment->markMatchingFeePaid($amount);

            return $assignment;
        } catch (\Exception $e) {
            Log::error('Failed to mark matching fee as paid', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create salary schedule for an accepted assignment.
     */
    protected function createSalarySchedule(MaidAssignment $assignment): void
    {
        if (!$assignment->salary_amount || !$assignment->isAccepted()) {
            return;
        }

        $maidWallet = $this->walletService->getOrCreateMaidWallet($assignment->maid_id);

        // Calculate next salary date based on maid's salary day
        $salaryDay = $maidWallet->salary_day ?? 1;
        $nextSalaryDate = $this->calculateNextSalaryDate($salaryDay);

        \App\Models\SalarySchedule::create([
            'assignment_id' => $assignment->id,
            'employer_id' => $assignment->employer_id,
            'maid_id' => $assignment->maid_id,
            'monthly_salary' => $assignment->salary_amount,
            'salary_day' => $maidWallet->salary_day ?? 1,
            'employment_start_date' => $assignment->started_at,
            'first_salary_date' => $nextSalaryDate,
            'current_period_start' => $assignment->started_at,
            'current_period_end' => $nextSalaryDate,
            'next_salary_due_date' => $nextSalaryDate,
            'reminder_days_before' => 3,
            'reminder_3_days_sent' => false,
            'reminder_1_day_sent' => false,
            'reminder_due_sent' => false,
            'escrow_funded' => false,
            'payment_status' => 'pending',
            'is_active' => true,
        ]);

        Log::info('Salary schedule created for assignment', [
            'assignment_id' => $assignment->id,
            'next_salary_date' => $nextSalaryDate,
        ]);
    }

    /**
     * Calculate next salary date based on salary day.
     */
    protected function calculateNextSalaryDate(int $salaryDay): \Carbon\Carbon
    {
        $now = now();
        $nextDate = $now->copy()->setDay($salaryDay);

        if ($nextDate->isPast()) {
            $nextDate->addMonth();
        }

        return $nextDate;
    }

    /**
     * Get assignment statistics for admin dashboard.
     */
    public function getAssignmentStatistics(): array
    {
        return [
            'total' => MaidAssignment::count(),
            'pending_acceptance' => MaidAssignment::pendingAcceptance()->count(),
            'accepted' => MaidAssignment::accepted()->count(),
            'active' => MaidAssignment::where('status', 'accepted')
                ->whereNull('completed_at')
                ->whereNull('cancelled_at')
                ->count(),
            'completed' => MaidAssignment::where('status', 'completed')->count(),
            'rejected' => MaidAssignment::where('status', 'rejected')->count(),
            'cancelled' => MaidAssignment::where('status', 'cancelled')->count(),
            'guarantee_matches' => MaidAssignment::guaranteeMatch()->count(),
            'direct_selections' => MaidAssignment::where('assignment_type', 'direct_selection')->count(),
        ];
    }

    /**
     * Get employer assignment overview for admin.
     */
    public function getEmployerOverview(int $employerId): array
    {
        $assignments = MaidAssignment::forEmployer($employerId)->get();

        return [
            'employer_id' => $employerId,
            'total_assignments' => $assignments->count(),
            'pending_acceptance' => $assignments->where('status', 'pending_acceptance')->count(),
            'active' => $assignments->where('status', 'accepted')
                ->whereNull('completed_at')
                ->whereNull('cancelled_at')
                ->count(),
            'completed' => $assignments->where('status', 'completed')->count(),
            'rejected' => $assignments->where('status', 'rejected')->count(),
            'cancelled' => $assignments->where('status', 'cancelled')->count(),
            'current_maid' => $assignments->where('status', 'accepted')
                ->whereNull('completed_at')
                ->whereNull('cancelled_at')
                ->first()?->maid,
        ];
    }
}
