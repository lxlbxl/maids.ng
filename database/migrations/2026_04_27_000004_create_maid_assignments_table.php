<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maid_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preference_id')->constrained('employer_preferences')->onDelete('cascade');
            $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('maid_id')->constrained('users')->onDelete('cascade');
            $table->string('assigned_by', 20); // 'ai', 'admin', 'employer', 'auto'
            $table->string('assignment_type', 30); // 'direct_selection', 'guarantee_match', 'manual'
            $table->string('status', 30)->default('pending_acceptance'); // pending_acceptance, accepted, rejected, completed, cancelled
            $table->timestamp('employer_accepted_at')->nullable();
            $table->timestamp('employer_rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('ai_match_score', 5, 2)->nullable();
            $table->json('ai_recommendation_reason')->nullable();
            $table->foreignId('salary_schedule_id')->nullable();
            $table->timestamp('maid_notified_at')->nullable();
            $table->timestamp('employer_notified_at')->nullable();
            $table->timestamp('matched_until')->nullable()->comment('When maid becomes available again');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employer_id', 'status']);
            $table->index(['maid_id', 'status']);
            $table->index(['preference_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maid_assignments');
    }
};
