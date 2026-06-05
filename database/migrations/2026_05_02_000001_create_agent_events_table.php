<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();

            $table->string('agent_name', 50);
            $table->string('event_type', 100);
            $table->enum('severity', ['info', 'success', 'warning', 'error', 'pending'])
                  ->default('info');
            $table->string('summary', 500);

            $table->json('detail')->nullable();

            $table->boolean('triggered_by_human')->default(false);

            $table->foreignId('triggered_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignId('related_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->string('related_model', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->boolean('requires_approval')->default(false);
            $table->boolean('approved')->nullable();

            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('approval_note')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();

            $table->decimal('estimated_cost_usd', 8, 6)->nullable();

            $table->string('llm_model', 100)->nullable();

            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('channel', 50)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['agent_name', 'created_at']);
            $table->index(['created_at']);
            $table->index(['agent_name', 'event_type']);
            $table->index(['requires_approval', 'approved']);
            $table->index(['related_model', 'related_id']);
            $table->index(['created_at', 'total_tokens']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_events');
    }
};
