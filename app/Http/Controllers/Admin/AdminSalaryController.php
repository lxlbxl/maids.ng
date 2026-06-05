<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminSalaryController extends Controller
{
    public function index()
    {
        $schedules = [];
        $stats = [
            'total_schedules' => 0,
            'total_paid' => 0,
            'total_pending' => 0,
            'total_overdue' => 0,
            'total_amount_scheduled' => 0,
            'total_amount_paid' => 0,
            'overdue_amount' => 0,
        ];

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('salary_schedules')) {
                $rawSchedules = DB::table('salary_schedules')
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get();

                $schedules = $rawSchedules->map(function ($s) {
                    $employer = $s->employer_id ? DB::table('users')->where('id', $s->employer_id)->select('name')->first() : null;
                    $maid = $s->maid_id ? DB::table('users')->where('id', $s->maid_id)->select('name')->first() : null;

                    $daysOverdue = 0;
                    if ($s->payment_status === 'overdue' && $s->next_salary_due_date) {
                        $daysOverdue = max(0, (int) now()->diffInDays($s->next_salary_due_date));
                    }

                    return [
                        'id' => $s->id,
                        'assignment' => [
                            'employer' => ['name' => $employer->name ?? 'Unknown'],
                            'maid' => ['name' => $maid->name ?? 'Unknown'],
                        ],
                        'amount' => (float) $s->monthly_salary,
                        'due_date' => $s->next_salary_due_date ?? $s->first_salary_date ?? now()->format('Y-m-d'),
                        'status' => $s->payment_status ?? 'pending',
                        'days_overdue' => $daysOverdue,
                    ];
                })->all();

                $stats = [
                    'total_schedules' => DB::table('salary_schedules')->count(),
                    'total_paid' => DB::table('salary_schedules')->where('payment_status', 'paid')->count(),
                    'total_pending' => DB::table('salary_schedules')->where('payment_status', 'pending')->count(),
                    'total_overdue' => DB::table('salary_schedules')->where('payment_status', 'overdue')->count(),
                    'total_amount_scheduled' => (float) DB::table('salary_schedules')->sum('monthly_salary'),
                    'total_amount_paid' => (float) DB::table('salary_payments')->where('status', 'completed')->sum('amount'),
                    'overdue_amount' => (float) DB::table('salary_schedules')->where('payment_status', 'overdue')->sum('monthly_salary'),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Salary data fetch failed: ' . $e->getMessage());
        }

        return Inertia::render('Admin/SalaryManagement', [
            'schedules' => $schedules,
            'stats' => $stats,
        ]);
    }

    public function nudge($id)
    {
        try {
            $schedule = DB::table('salary_schedules')->where('id', $id)->first();
            if (!$schedule) {
                return back()->withErrors(['message' => 'Salary schedule not found.']);
            }

            $employer = DB::table('users')->where('id', $schedule->employer_id)->first();
            if (!$employer) {
                return back()->withErrors(['message' => 'Employer not found.']);
            }

            $maid = DB::table('users')->where('id', $schedule->maid_id)->first();
            $maidName = $maid->name ?? 'your maid';
            $amountFormatted = number_format($schedule->monthly_salary, 2);

            DB::table('notifications')->insert([
                'user_id' => $employer->id,
                'type' => 'salary_reminder',
                'title' => 'Salary Payment Overdue',
                'message' => "Your salary payment of ₦{$amountFormatted} for {$maidName} is overdue. Please process payment as soon as possible to maintain your account standing.",
                'data' => json_encode([
                    'schedule_id' => $schedule->id,
                    'amount' => $schedule->monthly_salary,
                    'maid_name' => $maidName,
                    'nudged_by' => 'admin',
                    'nudged_at' => now()->toIso8601String(),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $notificationService = app(\App\Services\NotificationService::class);
                $notificationService->sendSms(
                    (object) $employer,
                    "Hi {$employer->name}, this is a reminder that your salary payment of ₦{$amountFormatted} for {$maidName} is overdue. Please process payment at your earliest convenience. - Maids.ng",
                    ['type' => 'salary_nudge', 'schedule_id' => $schedule->id],
                    'salary_reminder'
                );
            } catch (\Throwable $smsErr) {
                Log::info('SMS nudge skipped: ' . $smsErr->getMessage());
            }

            DB::table('salary_schedules')
                ->where('id', $id)
                ->update([
                    'last_reminder_sent_at' => now(),
                    'reminder_count' => DB::raw('reminder_count + 1'),
                    'updated_at' => now(),
                ]);

            try {
                DB::table('agent_activity_logs')->insert([
                    'agent_type' => 'admin_manual',
                    'action' => 'salary_nudge_sent',
                    'description' => "Salary nudge sent to {$employer->name} for schedule #{$id}",
                    'metadata' => json_encode(['schedule_id' => $id, 'employer_id' => $employer->id, 'admin_id' => auth()->id()]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
            }

            return back()->with('success', "Payment nudge sent to {$employer->name} successfully.");
        } catch (\Throwable $e) {
            Log::error('Salary nudge failed: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Failed to send nudge: ' . $e->getMessage()]);
        }
    }

    public function processPayment($id)
    {
        try {
            $schedule = DB::table('salary_schedules')->where('id', $id)->first();
            if (!$schedule) {
                return back()->withErrors(['message' => 'Salary schedule not found.']);
            }

            if ($schedule->payment_status === 'paid') {
                return back()->withErrors(['message' => 'This salary has already been paid.']);
            }

            $salaryService = app(\App\Services\SalaryManagementService::class);
            $result = $salaryService->processSalaryPayment(
                $schedule->assignment_id,
                (float) $schedule->monthly_salary,
                'Admin-processed salary payment for schedule #' . $id
            );

            if ($result) {
                DB::table('salary_schedules')
                    ->where('id', $id)
                    ->update([
                        'payment_status' => 'paid',
                        'updated_at' => now(),
                    ]);

                return redirect()->route('admin.salary')->with('success', 'Salary payment processed successfully.');
            }

            return back()->withErrors(['message' => 'Failed to process payment. Employer may have insufficient wallet balance.']);
        } catch (\Throwable $e) {
            Log::error('Salary process failed: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Payment processing failed: ' . $e->getMessage()]);
        }
    }

    public function markPaid($id)
    {
        try {
            $schedule = DB::table('salary_schedules')->where('id', $id)->first();
            if (!$schedule) {
                return back()->withErrors(['message' => 'Salary schedule not found.']);
            }

            DB::table('salary_schedules')
                ->where('id', $id)
                ->update([
                    'payment_status' => 'paid',
                    'updated_at' => now(),
                ]);

            try {
                DB::table('salary_payments')->insert([
                    'assignment_id' => $schedule->assignment_id,
                    'employer_id' => $schedule->employer_id,
                    'maid_id' => $schedule->maid_id,
                    'amount' => $schedule->monthly_salary,
                    'description' => 'Manually marked as paid by admin (ID: ' . auth()->id() . ')',
                    'paid_at' => now(),
                    'status' => 'completed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Could not record manual salary payment: ' . $e->getMessage());
            }

            try {
                DB::table('agent_activity_logs')->insert([
                    'agent_type' => 'admin_manual',
                    'action' => 'salary_marked_paid',
                    'description' => "Salary schedule #{$id} manually marked as paid",
                    'metadata' => json_encode(['schedule_id' => $id, 'admin_id' => auth()->id()]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
            }

            return redirect()->route('admin.salary')->with('success', 'Salary marked as paid.');
        } catch (\Throwable $e) {
            Log::error('Mark paid failed: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Failed to mark as paid: ' . $e->getMessage()]);
        }
    }

    public function export()
    {
        try {
            $schedules = DB::table('salary_schedules')
                ->orderByDesc('created_at')
                ->get();

            $csv = "ID,Employer ID,Maid ID,Monthly Salary,Payment Status,Due Date,Reminder Count,Created At\n";
            foreach ($schedules as $s) {
                $csv .= implode(',', [
                    $s->id,
                    $s->employer_id ?? '',
                    $s->maid_id ?? '',
                    $s->monthly_salary ?? 0,
                    $s->payment_status ?? 'unknown',
                    $s->next_salary_due_date ?? '',
                    $s->reminder_count ?? 0,
                    $s->created_at ?? '',
                ]) . "\n";
            }

            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="salary_report_' . now()->format('Y-m-d') . '.csv"',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }
}
