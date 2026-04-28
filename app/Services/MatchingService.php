<?php

namespace App\Services;

use App\Models\Maid;
use App\Models\MaidAssignment;
use App\Models\AiMatchingQueue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchingService
{
    protected $walletService;
    protected $notificationService;
    protected $salaryManagementService;

    public function __construct(
        WalletService $walletService,
        NotificationService $notificationService,
        SalaryManagementService $salaryManagementService
    ) {
        $this->walletService = $walletService;
        $this->notificationService = $notificationService;
        $this->salaryManagementService = $salaryManagementService;
    }

    /**
     * Process direct selection (auto-acceptance flow).
     * When employer selects maid from search, auto-accept and send SMS.
     */
    public function processDirectSelection(
        int $employerId,
        int $maidId,
        array $details = []
    ): ?MaidAssignment {
        try {
            DB::beginTransaction();

            $employer = User::find($employerId);
            $maid = Maid::find($maidId);

            if (!$employer || !$maid) {
                DB::rollBack();
                return null;
            }

            // Check if maid is available
            if (!$this->isMaidAvailable($maidId)) {
                DB::rollBack();
                return null;
            }

            // Create assignment with auto-accepted status
            $assignment = MaidAssignment::create([
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'type' => 'direct_selection',
                'status' => 'accepted', // Auto-accepted
                'employer_accepted_at' => now(),
                'maid_notified_at' => now(),
                'start_date' => $details['start_date'] ?? now(),
                'monthly_salary' => $details['monthly_salary'] ?? null,
                'notes' => $details['notes'] ?? null,
                'metadata' => json_encode([
                    'auto_accepted' => true,
                    'selected_from_search' => true,
                    'selection_time' => now()->toIso8601String(),
                ]),
            ]);

            // Mark maid as unavailable
            $this->markMaidUnavailable($maidId, $assignment->id);

            // Create salary schedule if salary provided
            if (!empty($details['monthly_salary'])) {
                $this->salaryManagementService->createSalarySchedule(
                    $assignment->id,
                    $details['monthly_salary'],
                    Carbon::parse($details['start_date'] ?? now())
                );
            }

            DB::commit();

            // Send SMS notification to maid (outside transaction)
            $this->notifyMaidOfAssignment($assignment, $maid, $employer);

            // Send confirmation to employer
            $this->notifyEmployerOfAutoAcceptance($assignment, $employer, $maid);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Direct selection failed', [
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Process guarantee match assignment (requires employer acceptance).
     * AI/Admin assigns maid, employer must accept/reject.
     */
    public function processGuaranteeMatchAssignment(
        int $employerId,
        int $maidId,
        int $assignedBy, // AI agent ID or admin user ID
        string $assignedByType, // 'ai' or 'admin'
        array $details = []
    ): ?MaidAssignment {
        try {
            DB::beginTransaction();

            $employer = User::find($employerId);
            $maid = Maid::find($maidId);

            if (!$employer || !$maid) {
                DB::rollBack();
                return null;
            }

            // Check if maid is available
            if (!$this->isMaidAvailable($maidId)) {
                DB::rollBack();
                return null;
            }

            // Create assignment with pending_acceptance status
            $assignment = MaidAssignment::create([
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'type' => 'guarantee_match',
                'status' => 'pending_acceptance',
                'assigned_by' => $assignedBy,
                'assigned_by_type' => $assignedByType,
                'assigned_at' => now(),
                'employer_response_deadline' => now()->addHours(48),
                'start_date' => $details['start_date'] ?? now()->addDays(7),
                'monthly_salary' => $details['monthly_salary'] ?? null,
                'notes' => $details['notes'] ?? null,
                'metadata' => json_encode([
                    'assigned_by' => $assignedByType,
                    'assignment_time' => now()->toIso8601String(),
                ]),
            ]);

            DB::commit();

            // Send notification to employer for acceptance (outside transaction)
            $this->notifyEmployerOfPendingAssignment($assignment, $employer, $maid);

            // Notify maid of potential assignment
            $this->notifyMaidOfPotentialAssignment($assignment, $maid, $employer);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Guarantee match assignment failed', [
                'employer_id' => $employerId,
                'maid_id' => $maidId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Employer accepts guarantee match assignment.
     */
    public function acceptAssignment(int $assignmentId, int $employerId): ?MaidAssignment
    {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::where('id', $assignmentId)
                ->where('employer_id', $employerId)
                ->where('status', 'pending_acceptance')
                ->first();

            if (!$assignment) {
                DB::rollBack();
                return null;
            }

            // Check if within deadline
            if ($assignment->isResponseOverdue()) {
                DB::rollBack();
                return null;
            }

            // Update assignment
            $assignment->update([
                'status' => 'accepted',
                'employer_accepted_at' => now(),
                'maid_notified_at' => now(),
            ]);

            // Mark maid as unavailable
            $this->markMaidUnavailable($assignment->maid_id, $assignment->id);

            // Create salary schedule if salary provided
            if ($assignment->monthly_salary) {
                $this->salaryManagementService->createSalarySchedule(
                    $assignment->id,
                    $assignment->monthly_salary,
                    Carbon::parse($assignment->start_date)
                );
            }

            DB::commit();

            // Send notifications
            $employer = User::find($employerId);
            $maid = Maid::find($assignment->maid_id);

            if ($maid && $employer) {
                $this->notifyMaidOfAssignment($assignment, $maid, $employer);
                $this->notifyEmployerOfAcceptanceConfirmation($assignment, $employer, $maid);
            }

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assignment acceptance failed', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Employer rejects guarantee match assignment.
     */
    public function rejectAssignment(
        int $assignmentId,
        int $employerId,
        string $reason = ''
    ): ?array {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::where('id', $assignmentId)
                ->where('employer_id', $employerId)
                ->where('status', 'pending_acceptance')
                ->first();

            if (!$assignment) {
                DB::rollBack();
                return null;
            }

            // Check if within deadline
            if ($assignment->isResponseOverdue()) {
                DB::rollBack();
                return null;
            }

            // Calculate refund amount (if any payment was made)
            $refundAmount = $this->calculateRefundAmount($assignment);

            // Process refund to employer wallet
            if ($refundAmount > 0) {
                $refundTransaction = $this->walletService->processAssignmentRefund(
                    $employerId,
                    $refundAmount,
                    $assignmentId,
                    $reason ?: 'Employer rejected assignment'
                );

                if (!$refundTransaction) {
                    DB::rollBack();
                    return null;
                }
            }

            // Update assignment
            $assignment->update([
                'status' => 'rejected',
                'employer_rejected_at' => now(),
                'rejection_reason' => $reason,
                'refund_amount' => $refundAmount,
                'refund_transaction_id' => $refundTransaction?->id,
            ]);

            DB::commit();

            // Trigger replacement search
            $replacementJob = $this->queueReplacementSearch($assignment);

            // Send notifications
            $employer = User::find($employerId);
            $maid = Maid::find($assignment->maid_id);

            if ($employer) {
                $this->notifyEmployerOfRejectionConfirmation($assignment, $employer, $refundAmount);
            }

            return [
                'assignment' => $assignment,
                'refund_amount' => $refundAmount,
                'replacement_queued' => $replacementJob !== null,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assignment rejection failed', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Queue replacement search for rejected assignment.
     */
    protected function queueReplacementSearch(MaidAssignment $assignment): ?AiMatchingQueue
    {
        try {
            return AiMatchingQueue::create([
                'employer_id' => $assignment->employer_id,
                'type' => 'replacement',
                'status' => 'pending',
                'priority' => 2, // Higher priority for replacements
                'requirements' => json_encode([
                    'original_assignment_id' => $assignment->id,
                    'rejected_maid_id' => $assignment->maid_id,
                    'rejection_reason' => $assignment->rejection_reason,
                    'original_requirements' => $this->getOriginalRequirements($assignment->employer_id),
                ]),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue replacement search', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Process replacement search.
     */
    public function processReplacementSearch(int $queueId): ?array
    {
        $queue = AiMatchingQueue::find($queueId);
        if (!$queue || $queue->type !== 'replacement') {
            return null;
        }

        $requirements = json_decode($queue->requirements, true);
        $employerId = $queue->employer_id;

        // Find available maids matching requirements
        $availableMaids = $this->findMatchingMaids($requirements, $employerId);

        // Exclude the rejected maid
        if (isset($requirements['rejected_maid_id'])) {
            $availableMaids = $availableMaids->where('id', '!=', $requirements['rejected_maid_id']);
        }

        $matches = $availableMaids->take(5)->get(); // Top 5 matches

        if ($matches->isEmpty()) {
            $queue->update([
                'status' => 'no_matches',
                'processed_at' => now(),
            ]);

            // Notify admin of no matches
            $this->notifyAdminOfNoMatches($queue);

            return [
                'success' => false,
                'message' => 'No matching maids found',
            ];
        }

        // Update queue with matches
        $queue->update([
            'status' => 'completed',
            'processed_at' => now(),
            'result' => json_encode([
                'matches_found' => $matches->count(),
                'maid_ids' => $matches->pluck('id')->toArray(),
            ]),
        ]);

        // Notify employer of replacement options
        $this->notifyEmployerOfReplacementOptions($queue, $matches);

        return [
            'success' => true,
            'matches' => $matches,
            'count' => $matches->count(),
        ];
    }

    /**
     * Find matching maids based on requirements.
     */
    protected function findMatchingMaids(array $requirements, int $employerId)
    {
        $query = Maid::where('is_available', true)
            ->where('status', 'active');

        // Apply filters from requirements
        if (!empty($requirements['skills'])) {
            $query->whereHas('skills', function ($q) use ($requirements) {
                $q->whereIn('name', $requirements['skills']);
            });
        }

        if (!empty($requirements['location'])) {
            $query->where('location', 'like', '%' . $requirements['location'] . '%');
        }

        if (!empty($requirements['experience_years'])) {
            $query->where('experience_years', '>=', $requirements['experience_years']);
        }

        if (!empty($requirements['age_range'])) {
            $query->whereBetween('age', $requirements['age_range']);
        }

        if (!empty($requirements['religion'])) {
            $query->where('religion', $requirements['religion']);
        }

        if (!empty($requirements['marital_status'])) {
            $query->where('marital_status', $requirements['marital_status']);
        }

        // Order by relevance (could be enhanced with AI scoring)
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Find the best matching maid for a preference.
     */
    public function findBestMatch(\App\Models\EmployerPreference $preference): ?array
    {
        $query = \App\Models\MaidProfile::where('availability_status', 'available')
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            });

        if (!empty($preference->help_types)) {
            foreach ($preference->help_types as $type) {
                $query->whereJsonContains('help_types', $type);
            }
        }

        if ($preference->state) {
            $query->where('state', $preference->state);
        }

        if ($preference->min_experience) {
            $query->where('experience_years', '>=', $preference->min_experience);
        }

        if ($preference->max_salary) {
            $query->where('expected_salary', '<=', $preference->max_salary);
        }

        if ($preference->schedule_type) {
            $query->where('schedule_preference', $preference->schedule_type);
        }

        $maid = $query->orderBy('rating', 'desc')
            ->orderBy('total_reviews', 'desc')
            ->first();

        if (!$maid) {
            return null;
        }

        return [
            'maid_id' => $maid->user_id,
            'score' => $maid->rating / 5,
            'reasoning' => "Matched based on: " . implode(', ', array_filter([
                $preference->help_types ? 'help types' : '',
                $preference->state ? 'location' : '',
                $preference->min_experience ? 'experience' : '',
            ])),
        ];
    }

    /**
     * Check if maid is available.
     */
    protected function isMaidAvailable(int $maidId): bool
    {
        $maid = Maid::find($maidId);
        return $maid && $maid->is_available && $maid->status === 'active';
    }

    /**
     * Mark maid as unavailable.
     */
    protected function markMaidUnavailable(int $maidId, int $assignmentId): void
    {
        Maid::where('id', $maidId)->update([
            'is_available' => false,
            'current_assignment_id' => $assignmentId,
            'updated_at' => now(),
        ]);
    }

    /**
     * Mark maid as available (when assignment ends).
     */
    public function markMaidAvailable(int $maidId): void
    {
        Maid::where('id', $maidId)->update([
            'is_available' => true,
            'current_assignment_id' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Calculate refund amount for rejected assignment.
     */
    protected function calculateRefundAmount(MaidAssignment $assignment): float
    {
        // For guarantee match, refund the matching fee
        // This could be stored in the assignment or calculated based on service type
        $matchingFee = config('services.matching.guarantee_fee', 50000); // Default 50,000 NGN

        return $matchingFee;
    }

    /**
     * Get original requirements from employer's search history or profile.
     */
    protected function getOriginalRequirements(int $employerId): array
    {
        // This would typically come from the employer's search history
        // or saved preferences
        $lastSearch = DB::table('employer_searches')
            ->where('employer_id', $employerId)
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastSearch ? json_decode($lastSearch->filters, true) : [];
    }

    /**
     * Notification methods.
     */
    protected function notifyMaidOfAssignment(MaidAssignment $assignment, Maid $maid, User $employer): void
    {
        $message = "Hello {$maid->first_name}, you have been assigned to {$employer->first_name} {$employer->last_name}. ";
        $message .= "Please contact them at {$employer->phone} to arrange your start date. ";
        $message .= "Assignment starts on " . Carbon::parse($assignment->start_date)->format('F j, Y') . ". ";
        $message .= "- Maids.ng";

        $this->notificationService->sendSms(
            $maid->user,
            $message,
            [
                'type' => 'assignment_confirmed',
                'assignment_id' => $assignment->id,
                'employer_id' => $employer->id,
            ],
            'assignment'
        );
    }

    protected function notifyEmployerOfAutoAcceptance(MaidAssignment $assignment, User $employer, Maid $maid): void
    {
        $message = "Hi {$employer->first_name}, you have successfully selected {$maid->first_name} {$maid->last_name}. ";
        $message .= "She has been notified and will contact you shortly. ";
        $message .= "Assignment starts on " . Carbon::parse($assignment->start_date)->format('F j, Y') . ". ";
        $message .= "- Maids.ng";

        $this->notificationService->sendSms(
            $employer,
            $message,
            [
                'type' => 'auto_acceptance_confirmation',
                'assignment_id' => $assignment->id,
                'maid_id' => $maid->id,
            ],
            'assignment'
        );
    }

    protected function notifyEmployerOfPendingAssignment(MaidAssignment $assignment, User $employer, Maid $maid): void
    {
        $deadline = Carbon::parse($assignment->employer_response_deadline)->format('F j, Y \a\t g:i A');

        $message = "Hi {$employer->first_name}, we have assigned {$maid->first_name} {$maid->last_name} to you. ";
        $message .= "Please accept or reject this match within 48 hours (by {$deadline}). ";
        $message .= "Login to your dashboard to respond. - Maids.ng";

        $this->notificationService->sendSms(
            $employer,
            $message,
            [
                'type' => 'pending_assignment',
                'assignment_id' => $assignment->id,
                'maid_id' => $maid->id,
                'deadline' => $assignment->employer_response_deadline,
            ],
            'assignment'
        );
    }

    protected function notifyMaidOfPotentialAssignment(MaidAssignment $assignment, Maid $maid, User $employer): void
    {
        $message = "Hello {$maid->first_name}, you have been proposed for a potential assignment with {$employer->first_name} {$employer->last_name}. ";
        $message .= "We are waiting for their confirmation. You will be notified once confirmed. - Maids.ng";

        $this->notificationService->sendSms(
            $maid->user,
            $message,
            [
                'type' => 'potential_assignment',
                'assignment_id' => $assignment->id,
                'employer_id' => $employer->id,
            ],
            'assignment'
        );
    }

    protected function notifyEmployerOfAcceptanceConfirmation(MaidAssignment $assignment, User $employer, Maid $maid): void
    {
        $message = "Hi {$employer->first_name}, you have accepted {$maid->first_name} {$maid->last_name}. ";
        $message .= "She has been notified and will contact you shortly. ";
        $message .= "Assignment starts on " . Carbon::parse($assignment->start_date)->format('F j, Y') . ". ";
        $message .= "- Maids.ng";

        $this->notificationService->sendSms(
            $employer,
            $message,
            [
                'type' => 'acceptance_confirmation',
                'assignment_id' => $assignment->id,
                'maid_id' => $maid->id,
            ],
            'assignment'
        );
    }

    protected function notifyEmployerOfRejectionConfirmation(MaidAssignment $assignment, User $employer, float $refundAmount): void
    {
        $message = "Hi {$employer->first_name}, you have rejected the assigned maid. ";

        if ($refundAmount > 0) {
            $message .= "A refund of N" . number_format($refundAmount, 2) . " has been credited to your wallet. ";
        }

        $message .= "We are searching for a replacement match for you. - Maids.ng";

        $this->notificationService->sendSms(
            $employer,
            $message,
            [
                'type' => 'rejection_confirmation',
                'assignment_id' => $assignment->id,
                'refund_amount' => $refundAmount,
            ],
            'assignment'
        );
    }

    protected function notifyEmployerOfReplacementOptions(AiMatchingQueue $queue, $matches): void
    {
        $employer = User::find($queue->employer_id);
        if (!$employer) {
            return;
        }

        $count = $matches->count();
        $message = "Hi {$employer->first_name}, we found {$count} replacement maid(s) matching your requirements. ";
        $message .= "Please login to your dashboard to view and select. - Maids.ng";

        $this->notificationService->sendSms(
            $employer,
            $message,
            [
                'type' => 'replacement_options',
                'queue_id' => $queue->id,
                'match_count' => $count,
            ],
            'matching'
        );
    }

    protected function notifyAdminOfNoMatches(AiMatchingQueue $queue): void
    {
        // This would notify admin via email or dashboard notification
        Log::warning('No replacement matches found', [
            'queue_id' => $queue->id,
            'employer_id' => $queue->employer_id,
        ]);
    }

    /**
     * Complete an assignment.
     */
    public function completeAssignment(int $assignmentId): ?MaidAssignment
    {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::find($assignmentId);
            if (!$assignment || $assignment->status !== 'active') {
                DB::rollBack();
                return null;
            }

            $assignment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Mark maid as available again
            $this->markMaidAvailable($assignment->maid_id);

            // Complete salary schedule
            $this->salaryManagementService->completeSalarySchedule($assignmentId);

            DB::commit();

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
    public function cancelAssignment(int $assignmentId, string $reason = ''): ?MaidAssignment
    {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::find($assignmentId);
            if (!$assignment || !in_array($assignment->status, ['active', 'accepted', 'pending_acceptance'])) {
                DB::rollBack();
                return null;
            }

            $assignment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Mark maid as available again
            $this->markMaidAvailable($assignment->maid_id);

            DB::commit();

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
}
