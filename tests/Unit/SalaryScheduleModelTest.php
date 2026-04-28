<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\MaidAssignment;
use App\Models\SalarySchedule;
use App\Models\EmployerWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryScheduleModelTest extends TestCase
{
    use RefreshDatabase;

    protected $employer;
    protected $maid;
    protected $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRoles();

        $this->employer = User::factory()->create(['role' => 'employer']);
        $this->employer->assignRole('employer');

        $this->maid = User::factory()->create(['role' => 'maid']);
        $this->maid->assignRole('maid');

        $this->assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted',
        ]);
    }

    public function test_calculate_next_salary_date_returns_future_date()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'salary_day' => 28,
        ]);

        $nextDate = $schedule->calculateNextSalaryDate();

        $this->assertInstanceOf(\Carbon\Carbon::class, $nextDate);
        $this->assertEquals(28, $nextDate->day);
        $this->assertTrue($nextDate->isFuture() || $nextDate->isToday());
    }

    public function test_calculate_next_salary_date_returns_next_month_if_day_passed()
    {
        $today = now();
        $passedDay = $today->day - 1; // Yesterday

        if ($passedDay < 1) {
            $passedDay = 15; // Use middle of month if we're at start
        }

        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'salary_day' => $passedDay,
        ]);

        $nextDate = $schedule->calculateNextSalaryDate();

        // Should be next month since the day has passed
        $this->assertTrue($nextDate->isAfter($today) || $nextDate->isToday());
    }

    public function test_should_send_reminder_returns_true_when_due()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'next_salary_due_date' => now()->addDays(3),
            'reminder_days_before' => 5,
            'payment_status' => 'pending',
            'is_active' => true,
        ]);

        $this->assertTrue($schedule->shouldSendReminder());
    }

    public function test_should_send_reminder_returns_false_when_not_active()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'next_salary_due_date' => now()->addDays(3),
            'reminder_days_before' => 5,
            'payment_status' => 'pending',
            'is_active' => false,
        ]);

        $this->assertFalse($schedule->shouldSendReminder());
    }

    public function test_should_send_reminder_returns_false_when_already_paid()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'next_salary_due_date' => now()->addDays(3),
            'reminder_days_before' => 5,
            'payment_status' => 'paid',
            'is_active' => true,
        ]);

        $this->assertFalse($schedule->shouldSendReminder());
    }

    public function test_advance_period_updates_dates_correctly()
    {
        $originalDueDate = now()->subDays(5);

        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'salary_day' => 28,
            'current_period_start' => now()->subMonth()->startOfMonth(),
            'current_period_end' => now()->subMonth()->endOfMonth(),
            'next_salary_due_date' => $originalDueDate,
            'payment_status' => 'paid',
            'reminder_count' => 2,
            'escalation_level' => 1,
        ]);

        $schedule->advancePeriod();
        $schedule->refresh();

        $this->assertEquals('pending', $schedule->payment_status);
        $this->assertEquals(0, $schedule->reminder_count);
        $this->assertEquals(0, $schedule->escalation_level);
        $this->assertNull($schedule->last_reminder_sent_at);
        $this->assertNull($schedule->next_reminder_scheduled_at);
        $this->assertTrue($schedule->current_period_start->isAfter($originalDueDate));
    }

    public function test_fund_escrow_with_sufficient_balance()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 100000,
            'escrow_balance' => 0,
            'currency' => 'NGN',
        ]);

        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'monthly_salary' => 50000,
            'payment_status' => 'pending',
        ]);

        $result = $schedule->fundEscrow(50000);

        $this->assertTrue($result);
        $schedule->refresh();
        $wallet->refresh();

        $this->assertEquals(50000, $schedule->escrow_amount);
        $this->assertTrue($schedule->escrow_funded);
        $this->assertNotNull($schedule->escrow_funded_at);
        $this->assertEquals('payment_initiated', $schedule->payment_status);
        $this->assertEquals(50000, $wallet->escrow_balance);
        $this->assertEquals(50000, $wallet->balance);
    }

    public function test_fund_escrow_with_insufficient_balance()
    {
        $wallet = EmployerWallet::create([
            'employer_id' => $this->employer->id,
            'balance' => 10000,
            'escrow_balance' => 0,
            'currency' => 'NGN',
        ]);

        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'monthly_salary' => 50000,
            'payment_status' => 'pending',
        ]);

        $result = $schedule->fundEscrow(50000);

        $this->assertFalse($result);
        $schedule->refresh();

        $this->assertEquals(0, $schedule->escrow_amount);
        $this->assertFalse($schedule->escrow_funded);
    }

    public function test_mark_reminder_sent_increments_count()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'reminder_count' => 0,
            'payment_status' => 'pending',
        ]);

        $schedule->markReminderSent();
        $schedule->refresh();

        $this->assertEquals(1, $schedule->reminder_count);
        $this->assertNotNull($schedule->last_reminder_sent_at);
        $this->assertEquals('reminder_sent', $schedule->payment_status);
        $this->assertNotNull($schedule->next_reminder_scheduled_at);
    }

    public function test_escalate_increases_level()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'escalation_level' => 0,
        ]);

        $schedule->escalate();
        $schedule->refresh();

        $this->assertEquals(1, $schedule->escalation_level);
        $this->assertNotNull($schedule->last_escalation_at);
    }

    public function test_is_salary_due_soon_returns_true_within_threshold()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'next_salary_due_date' => now()->addDays(5),
        ]);

        $this->assertTrue($schedule->isSalaryDueSoon(7));
    }

    public function test_is_salary_due_soon_returns_false_beyond_threshold()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'next_salary_due_date' => now()->addDays(15),
        ]);

        $this->assertFalse($schedule->isSalaryDueSoon(7));
    }

    public function test_get_payment_status_label_attribute()
    {
        $statuses = [
            'pending' => 'Pending',
            'reminder_sent' => 'Reminder Sent',
            'payment_initiated' => 'Payment Initiated',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'disputed' => 'Disputed',
        ];

        foreach ($statuses as $status => $expectedLabel) {
            $schedule = SalarySchedule::factory()->create([
                'assignment_id' => $this->assignment->id,
                'employer_id' => $this->employer->id,
                'maid_id' => $this->maid->id,
                'payment_status' => $status,
            ]);

            $this->assertEquals($expectedLabel, $schedule->payment_status_label);
        }
    }

    public function test_get_days_until_due_attribute()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'next_salary_due_date' => now()->addDays(10),
        ]);

        $this->assertEquals(10, $schedule->days_until_due);
    }

    public function test_salary_schedule_scopes()
    {
        SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'is_active' => true,
            'payment_status' => 'pending',
        ]);

        SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'is_active' => false,
        ]);

        $this->assertEquals(1, SalarySchedule::active()->count());
        $this->assertEquals(1, SalarySchedule::forEmployer($this->employer->id)->count());
        $this->assertEquals(1, SalarySchedule::forMaid($this->maid->id)->count());
    }

    public function test_salary_schedule_relationships()
    {
        $schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
        ]);

        $this->assertInstanceOf(MaidAssignment::class, $schedule->assignment);
        $this->assertInstanceOf(User::class, $schedule->employer);
        $this->assertInstanceOf(User::class, $schedule->maid);
    }
}
