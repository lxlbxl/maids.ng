<?php

namespace App\Services;

use App\Models\MaidAssignment;
use App\Models\SalarySchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalaryManagementService
{
    protected $walletService;
    protected $notificationService;

    public function __construct(
        WalletService $walletService,
        NotificationService $notificationService
    ) {
        $this->walletService = $walletService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create salary schedule for an assignment.
     */
    public function createSalarySchedule(
        int $assignmentId,
        float $monthlySalary,
        Carbon $startDate,
        ?Carbon $endDate = null
    ): SalarySchedule {
        // Calculate first salary due date (end of first month from start date)
        $firstDueDate = $startDate->copy()->endOfMonth();

        // If start date is near end of month, first salary might be next month
        if ($startDate->diffInDays($firstDueDate) < 7) {
            $firstDueDate = $startDate->copy()->addMonth()->endOfMonth();
        }

        return SalarySchedule::create([
            'assignment_id' => $assignmentId,
            'monthly_salary' => $monthlySalary,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'next_due_date' => $firstDueDate,
            'last_reminder_sent_at' => null,
            'reminder_count' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * Calculate salary from start date to next due date.
     */
    public function calculateSalaryDue(int $assignmentId): ?array
    {
        $schedule = SalarySchedule::where('assignment_id', $assignmentId)
            ->where('status', 'active')
            ->first();

        if (!$schedule) {
            return null;
        }

        $assignment = MaidAssignment::find($assignmentId);
        if (!$assignment) {
            return null;
        }

        $today = now();
        $nextDueDate = Carbon::parse($schedule->next_due_date);

        // Calculate days worked since last payment or start date
        $lastPaymentDate = $this->getLastPaymentDate($assignmentId);
        $startCalcDate = $lastPaymentDate ?? Carbon::parse($schedule->start_date);

        $daysWorked = $startCalcDate->diffInDays($today);
        $daysInMonth = $startCalcDate->daysInMonth;

        // Calculate pro-rated salary
        $dailyRate = $schedule->monthly_salary / $daysInMonth;
        $amountDue = round($dailyRate * $daysWorked, 2);

        return [
            'assignment_id' => $assignmentId,
            'maid_id' => $assignment->maid_id,
            'employer_id' => $assignment->employer_id,
            'monthly_salary' => $schedule->monthly_salary,
            'amount_due' => $amountDue,
            'days_worked' => $daysWorked,
            'start_calc_date' => $startCalcDate->format('Y-m-d'),
            'next_due_date' => $nextDueDate->format('Y-m-d'),
            'days_until_due' => $today->diffInDays($nextDueDate, false),
        ];
    }

    /**
     * Process salary reminders (3 days before, 1 day before, due date).
     */
    public function processSalaryReminders(): array
    {
        $schedules = SalarySchedule::where('status', 'active')
            ->whereNotNull('next_due_date')
            ->get();

        $remindersSent = 0;
        $errors = [];

        foreach ($schedules as $schedule) {
            try {
                $nextDueDate = Carbon::parse($schedule->next_due_date);
                $today = now();
                $daysUntilDue = $today->diffInDays($nextDueDate, false);

                // Check if reminder should be sent
                $shouldRemind = $this->shouldSendReminder($schedule, $daysUntilDue);

                if (!$shouldRemind) {
                    continue;
                }

                $assignment = MaidAssignment::find($schedule->assignment_id);
                if (!$assignment || $assignment->status !== 'active') {
                    continue;
                }

                $employer = User::find($assignment->employer_id);
                $maid = User::find($assignment->maid_id);

                if (!$employer || !$maid) {
                    continue;
                }

                // Calculate amount due
                $salaryInfo = $this->calculateSalaryDue($schedule->assignment_id);
                $amountDue = $salaryInfo['amount_due'] ?? $schedule->monthly_salary;

                // Send reminder
                $reminderType = $this->getReminderType($daysUntilDue);
                $message = $this->buildReminderMessage($employer, $maid, $amountDue, $nextDueDate, $reminderType);

                $result = $this->notificationService->sendSms(
                    $employer,
                    $message,
                    [
                        'type' => 'salary_reminder',
                        'reminder_type' => $reminderType,
                        'assignment_id' => $assignment->id,
                        'maid_id' => $maid->id,
                        'amount_due' => $amountDue,
                        'due_date' => $nextDueDate->format('Y-m-d'),
                    ],
                    'salary_reminder'
                );

                if ($result['success'] || $result['scheduled']) {
                    $schedule->update([
                        'last_reminder_sent_at' => now(),
                        'reminder_count' => $schedule->reminder_count + 1,
                    ]);
                    $remindersSent++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'assignment_id' => $schedule->assignment_id,
                    'error' => $e->getMessage(),
                ];
                Log::error('Failed to process salary reminder', [
                    'assignment_id' => $schedule->assignment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'reminders_sent' => $remindersSent,
            'total_schedules' => $schedules->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Determine if reminder should be sent based on timing and previous reminders.
     */
    protected function shouldSendReminder(SalarySchedule $schedule, int $daysUntilDue): bool
    {
        $lastReminder = $schedule->last_reminder_sent_at
            ? Carbon::parse($schedule->last_reminder_sent_at)
            : null;

        // Due date reminder (0 days)
        if ($daysUntilDue === 0) {
            // Send if no reminder sent today
            return !$lastReminder || !$lastReminder->isToday();
        }

        // 1 day before reminder
        if ($daysUntilDue === 1) {
            // Send if no reminder in last 2 days
            return !$lastReminder || $lastReminder->diffInDays(now()) >= 2;
        }

        // 3 days before reminder
        if ($daysUntilDue === 3) {
            // Send if no reminder in last 4 days
            return !$lastReminder || $lastReminder->diffInDays(now()) >= 4;
        }

        return false;
    }

    /**
     * Get reminder type based on days until due.
     */
    protected function getReminderType(int $daysUntilDue): string
    {
        return match ($daysUntilDue) {
            3 => '3_day_reminder',
            1 => '1_day_reminder',
            0 => 'due_date_reminder',
            default => 'reminder',
        };
    }

    /**
     * Build reminder message for employer.
     */
    protected function buildReminderMessage(
        User $employer,
        User $maid,
        float $amount,
        Carbon $dueDate,
        string $reminderType
    ): string {
        $maidName = $maid->first_name ?? 'your maid';
        $amountFormatted = number_format($amount, 2);
        $dueDateFormatted = $dueDate->format('F j, Y');

        return match ($reminderType) {
            '3_day_reminder' => "Hi {$employer->first_name}, this is a friendly reminder that the salary payment of N{$amountFormatted} for {$maidName} is due in 3 days ({$dueDateFormatted}). Please ensure your wallet has sufficient balance. - Maids.ng",
            '1_day_reminder' => "Hi {$employer->first_name}, reminder: Salary payment of N{$amountFormatted} for {$maidName} is due tomorrow ({$dueDateFormatted}). Please top up your wallet if needed. - Maids.ng",
            'due_date_reminder' => "Hi {$employer->first_name}, today is the salary payment due date for {$maidName} (N{$amountFormatted}). Payment will be processed automatically from your wallet. - Maids.ng",
            default => "Hi {$employer->first_name}, salary payment of N{$amountFormatted} for {$maidName} is due on {$dueDateFormatted}. - Maids.ng",
        };
    }

    /**
     * Process automatic salary payment on due date.
     */
    public function processAutomaticPayments(): array
    {
        $schedules = SalarySchedule::where('status', 'active')
            ->whereDate('next_due_date', '<=', now())
            ->get();

        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($schedules as $schedule) {
            try {
                $assignment = MaidAssignment::find($schedule->assignment_id);
                if (!$assignment || $assignment->status !== 'active') {
                    continue;
                }

                $salaryInfo = $this->calculateSalaryDue($schedule->assignment_id);
                $amountDue = $salaryInfo['amount_due'] ?? $schedule->monthly_salary;

                // Check employer balance
                if (!$this->walletService->employerHasSufficientBalance($assignment->employer_id, $amountDue)) {
                    // Notify employer of insufficient balance
                    $this->notifyInsufficientBalance($assignment, $amountDue);
                    $failed++;
                    continue;
                }

                // Process payment
                $result = $this->processSalaryPayment(
                    $assignment->id,
                    $amountDue,
                    'Automatic salary payment for ' . now()->format('F Y')
                );

                if ($result) {
                    // Update next due date
                    $newDueDate = Carbon::parse($schedule->next_due_date)->addMonth()->endOfMonth();
                    $schedule->update([
                        'next_due_date' => $newDueDate,
                        'last_reminder_sent_at' => null,
                        'reminder_count' => 0,
                    ]);
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'assignment_id' => $schedule->assignment_id,
                    'error' => $e->getMessage(),
                ];
                Log::error('Failed to process automatic salary payment', [
                    'assignment_id' => $schedule->assignment_id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'total' => $schedules->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Process manual salary payment.
     */
    public function processSalaryPayment(
        int $assignmentId,
        float $amount,
        string $description
    ): ?array {
        try {
            DB::beginTransaction();

            $assignment = MaidAssignment::find($assignmentId);
            if (!$assignment || $assignment->status !== 'active') {
                DB::rollBack();
                return null;
            }

            // Debit employer wallet
            $employerTransaction = $this->walletService->debitEmployerWallet(
                $assignment->employer_id,
                $amount,
                $description,
                $assignmentId,
                'salary_payment'
            );

            if (!$employerTransaction) {
                DB::rollBack();
                return null;
            }

            // Credit maid wallet
            $maidTransaction = $this->walletService->creditMaidWallet(
                $assignment->maid_id,
                $amount,
                "Salary payment from employer #{$assignment->employer_id}",
                $assignmentId,
                'salary_earned'
            );

            if (!$maidTransaction) {
                DB::rollBack();
                return null;
            }

            // Record payment
            DB::table('salary_payments')->insert([
                'assignment_id' => $assignmentId,
                'employer_id' => $assignment->employer_id,
                'maid_id' => $assignment->maid_id,
                'amount' => $amount,
                'description' => $description,
                'employer_transaction_id' => $employerTransaction->id,
                'maid_transaction_id' => $maidTransaction->id,
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Notify both parties
            $this->notifyPaymentCompleted($assignment, $amount);

            DB::commit();

            return [
                'employer_transaction' => $employerTransaction,
                'maid_transaction' => $maidTransaction,
                'amount' => $amount,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process salary payment', [
                'assignment_id' => $assignmentId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Notify employer of insufficient balance.
     */
    protected function notifyInsufficientBalance(MaidAssignment $assignment, float $amount): void
    {
        $employer = User::find($assignment->employer_id);
        $maid = User::find($assignment->maid_id);

        if (!$employer) {
            return;
        }

        $maidName = $maid->first_name ?? 'your maid';
        $amountFormatted = number_format($amount, 2);

        $message = "Hi {$employer->first_name}, we were unable to process the salary payment of N{$amountFormatted} for {$maidName} due to insufficient wallet balance. Please top up your wallet to avoid service interruption. - Maids.ng";

        $this->notificationService->sendSms(
            $employer,
            $message,
            [
                'type' => 'insufficient_balance',
                'assignment_id' => $assignment->id,
                'amount' => $amount,
            ],
            'payment_failed'
        );
    }

    /**
     * Notify both parties of completed payment.
     */
    protected function notifyPaymentCompleted(MaidAssignment $assignment, float $amount): void
    {
        $employer = User::find($assignment->employer_id);
        $maid = User::find($assignment->maid_id);

        $amountFormatted = number_format($amount, 2);

        // Notify employer
        if ($employer) {
            $message = "Hi {$employer->first_name}, salary payment of N{$amountFormatted} has been successfully processed and transferred to your maid's wallet. Thank you for using Maids.ng";

            $this->notificationService->sendSms(
                $employer,
                $message,
                [
                    'type' => 'payment_completed',
                    'assignment_id' => $assignment->id,
                    'amount' => $amount,
                ],
                'payment_success'
            );
        }

        // Notify maid
        if ($maid) {
            $message = "Hi {$maid->first_name}, your salary of N{$amountFormatted} has been credited to your wallet. You can request a withdrawal at any time. - Maids.ng";

            $this->notificationService->sendSms(
                $maid,
                $message,
                [
                    'type' => 'salary_received',
                    'assignment_id' => $assignment->id,
                    'amount' => $amount,
                ],
                'salary_credit'
            );
        }
    }

    /**
     * Get last payment date for an assignment.
     */
    protected function getLastPaymentDate(int $assignmentId): ?Carbon
    {
        $lastPayment = DB::table('salary_payments')
            ->where('assignment_id', $assignmentId)
            ->orderBy('paid_at', 'desc')
            ->first();

        return $lastPayment ? Carbon::parse($lastPayment->paid_at) : null;
    }

    /**
     * Get salary schedule for assignment.
     */
    public function getSalarySchedule(int $assignmentId): ?SalarySchedule
    {
        return SalarySchedule::where('assignment_id', $assignmentId)->first();
    }

    /**
     * Update salary schedule.
     */
    public function updateSalarySchedule(
        int $assignmentId,
        array $data
    ): ?SalarySchedule {
        $schedule = SalarySchedule::where('assignment_id', $assignmentId)->first();

        if (!$schedule) {
            return null;
        }

        $schedule->update($data);
        return $schedule;
    }

    /**
     * Mark salary schedule as completed.
     */
    public function completeSalarySchedule(int $assignmentId): ?SalarySchedule
    {
        $schedule = SalarySchedule::where('assignment_id', $assignmentId)->first();

        if (!$schedule) {
            return null;
        }

        $schedule->update([
            'status' => 'completed',
            'end_date' => now(),
        ]);

        return $schedule;
    }

    /**
     * Get overdue salaries (admin only).
     */
    public function getOverdueSalaries(): array
    {
        $schedules = SalarySchedule::where('payment_status', 'overdue')
            ->orWhere(function ($q) {
                $q->where('payment_status', 'pending')
                    ->whereNotNull('next_salary_due_date')
                    ->where('next_salary_due_date', '<', now());
            })
            ->with(['assignment.employer', 'assignment.maid'])
            ->orderBy('next_salary_due_date')
            ->get();

        return $schedules->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'assignment_id' => $schedule->assignment_id,
                'employer_id' => $schedule->employer_id,
                'maid_id' => $schedule->maid_id,
                'monthly_salary' => $schedule->monthly_salary,
                'due_date' => $schedule->next_salary_due_date,
                'days_overdue' => $schedule->next_salary_due_date ? now()->diffInDays($schedule->next_salary_due_date, false) : 0,
                'escalation_level' => $schedule->escalation_level,
                'employer' => $schedule->assignment?->employer?->name ?? 'Unknown',
                'maid' => $schedule->assignment?->maid?->name ?? 'Unknown',
            ];
        })->toArray();
    }

    /**
     * Process 3-day salary reminders.
     */
    public function processThreeDayReminders(): int
    {
        $schedules = SalarySchedule::where('payment_status', 'pending')
            ->whereNotNull('next_salary_due_date')
            ->whereDate('next_salary_due_date', '=', now()->addDays(3)->toDateString())
            ->get();

        $sent = 0;

        foreach ($schedules as $schedule) {
            try {
                $employer = User::find($schedule->employer_id);
                if (!$employer) {
                    continue;
                }

                $amountFormatted = number_format($schedule->monthly_salary, 2);
                $dueDateFormatted = $schedule->next_salary_due_date->format('F j, Y');
                $message = "Hi {$employer->name}, this is a friendly reminder that the salary payment of N{$amountFormatted} is due in 3 days ({$dueDateFormatted}). Please ensure your wallet has sufficient balance. - Maids.ng";

                $result = $this->notificationService->sendSms(
                    $employer,
                    $message,
                    [
                        'type' => 'salary_reminder',
                        'reminder_type' => '3_day_reminder',
                        'assignment_id' => $schedule->assignment_id,
                        'amount_due' => $schedule->monthly_salary,
                        'due_date' => $schedule->next_salary_due_date->format('Y-m-d'),
                    ],
                    'salary_reminder'
                );

                if ($result['success'] || $result['scheduled']) {
                    $schedule->markReminderSent();
                    $sent++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to send 3-day salary reminder', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Process 1-day salary reminders.
     */
    public function processOneDayReminders(): int
    {
        $schedules = SalarySchedule::where('payment_status', 'pending')
            ->whereNotNull('next_salary_due_date')
            ->whereDate('next_salary_due_date', '=', now()->addDay()->toDateString())
            ->get();

        $sent = 0;

        foreach ($schedules as $schedule) {
            try {
                $employer = User::find($schedule->employer_id);
                if (!$employer) {
                    continue;
                }

                $amountFormatted = number_format($schedule->monthly_salary, 2);
                $dueDateFormatted = $schedule->next_salary_due_date->format('F j, Y');
                $message = "Hi {$employer->name}, reminder: Salary payment of N{$amountFormatted} is due tomorrow ({$dueDateFormatted}). Please top up your wallet if needed. - Maids.ng";

                $result = $this->notificationService->sendSms(
                    $employer,
                    $message,
                    [
                        'type' => 'salary_reminder',
                        'reminder_type' => '1_day_reminder',
                        'assignment_id' => $schedule->assignment_id,
                        'amount_due' => $schedule->monthly_salary,
                        'due_date' => $schedule->next_salary_due_date->format('Y-m-d'),
                    ],
                    'salary_reminder'
                );

                if ($result['success'] || $result['scheduled']) {
                    $schedule->markReminderSent();
                    $sent++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to send 1-day salary reminder', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Process due salary payments.
     */
    public function processDueSalaries(): int
    {
        $schedules = SalarySchedule::where('payment_status', 'pending')
            ->whereNotNull('next_salary_due_date')
            ->whereDate('next_salary_due_date', '<=', now()->toDateString())
            ->get();

        $processed = 0;

        foreach ($schedules as $schedule) {
            try {
                $assignment = MaidAssignment::find($schedule->assignment_id);
                if (!$assignment || $assignment->status !== 'active') {
                    continue;
                }

                if (!$this->walletService->employerHasSufficientBalance($schedule->employer_id, $schedule->monthly_salary)) {
                    $this->notifyInsufficientBalance($assignment, $schedule->monthly_salary);
                    $schedule->payment_status = 'overdue';
                    $schedule->escalate();
                    $schedule->save();
                    continue;
                }

                $result = $this->processSalaryPayment(
                    $schedule->assignment_id,
                    $schedule->monthly_salary,
                    'Automatic salary payment for ' . now()->format('F Y')
                );

                if ($result) {
                    $schedule->advancePeriod();
                    $processed++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process due salary', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Get payment history for assignment.
     */
    public function getPaymentHistory(int $assignmentId): array
    {
        $payments = DB::table('salary_payments')
            ->where('assignment_id', $assignmentId)
            ->orderBy('paid_at', 'desc')
            ->get();

        return $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'description' => $payment->description,
                'paid_at' => $payment->paid_at,
            ];
        })->toArray();
    }

    /**
     * Get upcoming salary payments for employer.
     */
    public function getUpcomingPaymentsForEmployer(int $employerId): array
    {
        $assignments = MaidAssignment::where('employer_id', $employerId)
            ->where('status', 'active')
            ->pluck('id');

        $schedules = SalarySchedule::whereIn('assignment_id', $assignments)
            ->where('status', 'active')
            ->whereDate('next_due_date', '>=', now())
            ->orderBy('next_due_date')
            ->get();

        return $schedules->map(function ($schedule) {
            $assignment = MaidAssignment::find($schedule->assignment_id);
            $maid = $assignment ? User::find($assignment->maid_id) : null;

            return [
                'assignment_id' => $schedule->assignment_id,
                'maid_name' => $maid ? $maid->first_name . ' ' . $maid->last_name : 'Unknown',
                'monthly_salary' => $schedule->monthly_salary,
                'next_due_date' => $schedule->next_due_date,
                'days_until_due' => now()->diffInDays(Carbon::parse($schedule->next_due_date), false),
            ];
        })->toArray();
    }
}
