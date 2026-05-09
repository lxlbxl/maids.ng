<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_task_queue', function (Blueprint $table) {
            $table->id();

            $table->string('agent_name', 50);
            $table->string('task_type', 100);

            $table->enum('reason', [
                'agent_disabled',
                'agent_error',
                'hitl_required',
                'manual_override',
                'ai_downtime',
            ]);

            $table->json('task_payload');

            $table->string('description', 500);

            $table->unsignedTinyInteger('priority')->default(3);

            $table->enum('status', [
                'pending', 'assigned', 'in_progress',
                'completed', 'skipped', 'delegated'
            ])->default('pending');

            $table->foreignId('assigned_to')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('completed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('completion_notes')->nullable();

            $table->string('original_job_class', 255)->nullable();
            $table->json('original_job_payload')->nullable();

            $table->foreignId('triggered_by_event_id')
                  ->nullable()
                  ->constrained('agent_events')
                  ->nullOnDelete();

            $table->foreignId('related_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('due_by')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'created_at']);
            $table->index(['agent_name', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('due_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_task_queue');
    }
};
