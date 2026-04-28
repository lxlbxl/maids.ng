<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\SalarySchedule;
use App\Models\SalaryPayment;
use App\Models\MaidAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $employer;
    protected $maid;
    protected $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRoles();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');

        $this->employer = User::factory()->create(['role' => 'employer']);
        $this->employer->assignRole('employer');

        $this->maid = User::factory()->create(['role' => 'maid']);
        $this->maid->assignRole('maid');

        $this->assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted'
        ]);

        $this->schedule = SalarySchedule::factory()->create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'payment_status' => 'pending'
        ]);
    }

    public function test_admin_can_view_salary_schedules()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/salary/schedules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'schedules'
                ]
            ]);
    }

    public function test_maid_can_view_own_salary_history()
    {
        $schedule = SalarySchedule::create([
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'amount' => 50000,
            'due_date' => now()->subDays(5),
            'status' => 'paid'
        ]);

        SalaryPayment::create([
            'schedule_id' => $schedule->id,
            'maid_id' => $this->maid->id,
            'amount' => 50000,
            'status' => 'completed',
            'payment_date' => now()->subDays(5)
        ]);

        $response = $this->actingAs($this->maid)
            ->getJson('/api/v1/salary/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'payments'
                ]
            ]);
    }
}
