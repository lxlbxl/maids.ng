<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_matching_queue', function (Blueprint $table) {
            $table->id();

            // Job identification
            $table->uuid('job_id')->unique();
            $table->string('job_type'); // 'auto_match', 'replacement_search', 'guarantee_match', 'status_check', 'reminder_send'

            // References
            $table->foreignId('employer_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('maid_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('preference_id')->nullable()->constrained('employer_preferences')->onDelete('cascade');
            $table->foreignId('assignment_id')->nullable()->constrained('maid_assignments')->onDelete('cascade');

            // Job priority and scheduling
            $table->integer('priority')->default(5); // 1-10, 1 = highest
            $table->timestamp('scheduled_at'); // When job should run (timezone-aware)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Status tracking
            $table->enum('status', [
                'pending',      // Waiting to be processed
                'scheduled',    // Scheduled for future processing
                'processing',   // Currently being processed
                'completed',    // Successfully completed
                'failed',       // Failed after retries
                'cancelled',    // Manually cancelled
                'paused'        // Temporarily paused
            ])->default('pending');

            // Retry logic
            $table->integer('attempt_count')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestamp('next_attempt_at')->nullable();
            $table->integer('retry_delay_minutes')->default(5);

            // Job payload and results
            $table->json('payload')->nullable(); // Input parameters
            $table->json('result')->nullable(); // Output/result data
            $table->json('match_candidates')->nullable(); // For matching jobs, store candidate IDs and scores
            $table->foreignId('selected_maid_id')->nullable()->constrained('users')->onDelete('set null');

            // AI processing details
            $table->decimal('ai_confidence_score', 5, 2)->nullable(); // 0.00 - 100.00
            $table->text('ai_reasoning')->nullable(); // AI's explanation for match
            $table->json('ai_analysis_data')->nullable(); // Detailed AI analysis

            // Error handling
            $table->text('last_error')->nullable();
            $table->json('error_log')->nullable(); // Array of all errors
            $table->string('failure_category')->nullable(); // 'api_error', 'timeout', 'validation', 'no_matches', etc.

            // Processing metadata
            $table->string('processed_by_instance')->nullable(); // Server/instance ID
            $table->integer('processing_duration_ms')->nullable(); // How long job took
            $table->string('worker_pid')->nullable(); // Process ID

            // Context for AI follow-ups
            $table->json('context_snapshot')->nullable(); // Snapshot of relevant data at job creation
            $table->foreignId('parent_job_id')->nullable()->constrained('ai_matching_queue')->onDelete('set null'); // For related jobs
            $table->integer('job_chain_sequence')->default(0); // If part of a chain of jobs

            // Admin review
            $table->boolean('requires_review')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->enum('review_decision', ['approved', 'rejected', 'modified'])->nullable();

            // Notifications
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('notification_sent_at')->nullable();
            $table->string('notification_channel')->nullable(); // 'sms', 'email', 'push', 'whatsapp'

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient querying
            $table->index(['status', 'scheduled_at']);
            $table->index(['job_type', 'status']);
            $table->index(['employer_id', 'status']);
            $table->index(['priority', 'scheduled_at']);
            $table->index('job_id');
            $table->index(['status', 'attempt_count', 'next_attempt_at']); // For retry logic
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_matching_queue');
    }
};
