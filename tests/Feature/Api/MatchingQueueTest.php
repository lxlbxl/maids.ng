<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\AiMatchingQueue;
use App\Models\EmployerPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingQueueTest extends TestCase
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

    public function test_employer_can_request_matching()
    {
        $preference = EmployerPreference::factory()->create([
            'employer_id' => $this->employer->id,
        ]);

        $response = $this->actingAs($this->employer)
            ->postJson('/api/v1/matching/request', [
                'preference_id' => $preference->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.job_id', fn($id) => !empty($id));
    }

    public function test_employer_can_check_matching_status()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'processing',
            'priority' => 5,
            'attempt_count' => 1,
            'max_attempts' => 3,
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson("/api/v1/matching/status/{$job->job_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'processing',
                    'job_id' => $job->job_id,
                ]
            ]);
    }

    public function test_employer_can_get_matching_results()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'completed',
            'priority' => 5,
            'match_candidates' => [
                ['maid_id' => $this->maid->id, 'score' => 0.95],
            ],
            'selected_maid_id' => $this->maid->id,
            'ai_confidence_score' => 0.95,
            'ai_reasoning' => 'Strong match based on skills and location',
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson("/api/v1/matching/results/{$job->job_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_admin_can_view_matching_queue_statistics()
    {
        AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'pending',
            'priority' => 5,
        ]);

        AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'completed',
            'priority' => 5,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/matching/queue');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_can_view_matching_statistics()
    {
        AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'completed',
            'priority' => 5,
            'ai_confidence_score' => 0.95,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/matching/statistics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_can_retry_failed_matching_job()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'failed',
            'priority' => 5,
            'attempt_count' => 3,
            'max_attempts' => 3,
            'last_error' => 'AI service timeout',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/ai-matching/jobs/{$job->job_id}/retry");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $job->refresh();
        $this->assertEquals('pending', $job->status);
    }

    public function test_admin_can_cancel_matching_job()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'pending',
            'priority' => 5,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/ai-matching/jobs/{$job->job_id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $job->refresh();
        $this->assertEquals('cancelled', $job->status);
    }

    public function test_admin_can_view_matching_job_detail()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'completed',
            'priority' => 5,
            'ai_confidence_score' => 0.95,
            'ai_reasoning' => 'Strong match',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/ai-matching/jobs/{$job->job_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_matching_queue_model_methods()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'pending',
            'priority' => 5,
            'attempt_count' => 0,
            'max_attempts' => 3,
        ]);

        // Test status checks
        $this->assertTrue($job->isPending());
        $this->assertFalse($job->isProcessing());
        $this->assertFalse($job->isCompleted());
        $this->assertFalse($job->isFailed());
        $this->assertTrue($job->canRetry());

        // Mark as processing
        $job->markProcessing();
        $job->refresh();
        $this->assertTrue($job->isProcessing());
        $this->assertEquals(1, $job->attempt_count);

        // Mark as completed
        $job->markCompleted(['maid_id' => $this->maid->id]);
        $job->refresh();
        $this->assertTrue($job->isCompleted());
        $this->assertNotNull($job->completed_at);
    }

    public function test_matching_queue_job_lifecycle()
    {
        // Create pending job
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'pending',
            'priority' => 5,
            'attempt_count' => 0,
            'max_attempts' => 3,
        ]);

        $this->assertEquals('pending', $job->status);

        // Mark as processing
        $job->markProcessing();
        $job->refresh();
        $this->assertEquals('processing', $job->status);
        $this->assertEquals(1, $job->attempt_count);
        $this->assertNotNull($job->started_at);

        // Set match candidates
        $job->setMatchCandidates([
            ['maid_id' => $this->maid->id, 'score' => 0.95],
        ]);
        $job->refresh();
        $this->assertNotNull($job->match_candidates);

        // Set AI results
        $job->setAiResults(0.95, 'Strong match based on skills');
        $job->refresh();
        $this->assertEquals(0.95, $job->ai_confidence_score);
        $this->assertEquals('Strong match based on skills', $job->ai_reasoning);

        // Mark as completed
        $job->markCompleted(['selected_maid_id' => $this->maid->id]);
        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->completed_at);
    }

    public function test_matching_queue_job_failure_and_retry()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'processing',
            'priority' => 5,
            'attempt_count' => 1,
            'max_attempts' => 3,
            'retry_delay_minutes' => 30,
        ]);

        // Mark as failed
        $job->markFailed('AI service timeout', 'service_unavailable');
        $job->refresh();

        // Should be pending for retry since attempt_count < max_attempts
        $this->assertEquals('pending', $job->status);
        $this->assertNotNull($job->next_attempt_at);
        $this->assertEquals('AI service timeout', $job->last_error);
        $this->assertEquals('service_unavailable', $job->failure_category);
    }

    public function test_matching_queue_job_permanent_failure()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'processing',
            'priority' => 5,
            'attempt_count' => 3,
            'max_attempts' => 3,
        ]);

        // Mark as failed - should be permanent since max attempts reached
        $job->markFailed('AI service timeout', 'service_unavailable');
        $job->refresh();

        $this->assertEquals('failed', $job->status);
        $this->assertFalse($job->canRetry());
    }

    public function test_matching_queue_job_scheduling()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'pending',
            'priority' => 5,
        ]);

        $scheduledTime = now()->addHours(2);
        $job->scheduleFor($scheduledTime);
        $job->refresh();

        $this->assertEquals('scheduled', $job->status);
        $this->assertEquals($scheduledTime->format('Y-m-d H:i:s'), $job->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_matching_queue_job_cancellation()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'pending',
            'priority' => 5,
        ]);

        $job->cancel();
        $job->refresh();

        $this->assertEquals('cancelled', $job->status);
    }

    public function test_matching_queue_job_review_workflow()
    {
        $job = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'completed',
            'priority' => 5,
            'ai_confidence_score' => 0.65,
        ]);

        // Mark for review
        $job->markForReview();
        $job->refresh();
        $this->assertTrue($job->requires_review);

        // Review the job
        $job->review($this->admin->id, 'approved', 'Good match after manual review');
        $job->refresh();

        $this->assertFalse($job->requires_review);
        $this->assertEquals($this->admin->id, $job->reviewed_by);
        $this->assertEquals('approved', $job->review_decision);
        $this->assertEquals('Good match after manual review', $job->review_notes);
    }

    public function test_matching_queue_child_job_creation()
    {
        $parentJob = AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'completed',
            'priority' => 5,
        ]);

        $childJob = $parentJob->createChildJob('follow_up', ['action' => 'send_notification'], 3);

        $this->assertNotNull($childJob);
        $this->assertEquals($parentJob->id, $childJob->parent_job_id);
        $this->assertEquals('follow_up', $childJob->job_type);
        $this->assertEquals(1, $childJob->job_chain_sequence);
    }

    public function test_matching_queue_scopes()
    {
        AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'pending',
            'priority' => 5,
        ]);

        AiMatchingQueue::create([
            'job_type' => 'auto_match',
            'employer_id' => $this->employer->id,
            'status' => 'processing',
            'priority' => 3,
        ]);

        AiMatchingQueue::create([
            'job_type' => 'replacement_search',
            'employer_id' => $this->employer->id,
            'status' => 'completed',
            'priority' => 5,
        ]);

        $this->assertEquals(1, AiMatchingQueue::pending()->count());
        $this->assertEquals(1, AiMatchingQueue::processing()->count());
        $this->assertEquals(1, AiMatchingQueue::completed()->count());
        $this->assertEquals(1, AiMatchingQueue::ofType('auto_match')->count());
        $this->assertEquals(1, AiMatchingQueue::highPriority()->count());
    }
}
