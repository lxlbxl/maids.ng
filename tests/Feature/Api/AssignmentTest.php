<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\MaidAssignment;
use App\Models\EmployerPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected $employer;
    protected $maid;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRoles();

        $this->employer = User::factory()->create(['role' => 'employer']);
        $this->employer->assignRole('employer');

        $this->maid = User::factory()->create(['role' => 'maid']);
        $this->maid->assignRole('maid');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    public function test_employer_can_view_their_assignments()
    {
        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson('/api/v1/assignments');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.id', $assignment->id);
    }

    public function test_employer_can_view_assignment_detail()
    {
        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson("/api/v1/assignments/{$assignment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $assignment->id,
                    'status' => 'accepted',
                ]
            ]);
    }

    public function test_employer_can_accept_assignment()
    {
        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'pending_acceptance',
        ]);

        $response = $this->actingAs($this->employer)
            ->postJson("/api/v1/assignments/{$assignment->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('maid_assignments', [
            'id' => $assignment->id,
            'status' => 'accepted',
        ]);
    }

    public function test_employer_can_reject_assignment()
    {
        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'pending_acceptance',
        ]);

        $response = $this->actingAs($this->employer)
            ->postJson("/api/v1/assignments/{$assignment->id}/reject", [
                'reason' => 'Not a good fit'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('maid_assignments', [
            'id' => $assignment->id,
            'status' => 'rejected',
        ]);
    }

    public function test_employer_cannot_view_other_employers_assignments()
    {
        $otherEmployer = User::factory()->create(['role' => 'employer']);
        $otherEmployer->assignRole('employer');

        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $otherEmployer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson("/api/v1/assignments/{$assignment->id}");

        $response->assertStatus(403);
    }

    public function test_maid_can_view_their_assignments()
    {
        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->maid)
            ->getJson('/api/v1/maid/assignments');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.id', $assignment->id);
    }

    public function test_admin_can_view_all_assignments()
    {
        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/assignments');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.id', $assignment->id);
    }

    public function test_admin_can_cancel_assignment()
    {
        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/assignments/{$assignment->id}/cancel", [
                'reason' => 'Administrative cancellation'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('maid_assignments', [
            'id' => $assignment->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_assignment_statistics_are_returned()
    {
        MaidAssignment::factory()->count(3)->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/assignments/statistics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.total', 3);
    }

    public function test_assignment_lifecycle_from_pending_to_completed()
    {
        // Create assignment as pending acceptance
        $assignment = MaidAssignment::factory()->create([
            'employer_id' => $this->employer->id,
            'maid_id' => $this->maid->id,
            'status' => 'pending_acceptance',
        ]);

        $this->assertEquals('pending_acceptance', $assignment->status);

        // Accept the assignment
        $response = $this->actingAs($this->employer)
            ->postJson("/api/v1/assignments/{$assignment->id}/accept");

        $response->assertStatus(200);
        $assignment->refresh();
        $this->assertEquals('accepted', $assignment->status);

        // Complete the assignment
        $response = $this->actingAs($this->employer)
            ->postJson("/api/v1/assignments/{$assignment->id}/complete");

        $response->assertStatus(200);
        $assignment->refresh();
        $this->assertEquals('completed', $assignment->status);
        $this->assertNotNull($assignment->completed_at);
    }
}
