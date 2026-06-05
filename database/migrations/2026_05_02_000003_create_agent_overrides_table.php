<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_overrides', function (Blueprint $table) {
            $table->id();

            $table->string('agent_name', 50)->unique();

            $table->enum('mode', ['active', 'supervised', 'paused', 'readonly'])
                  ->default('active');

            $table->json('supervised_action_types')->nullable();

            $table->boolean('auto_route_to_human')->default(true);

            $table->boolean('kill_switch')->default(false);

            $table->string('override_reason', 500)->nullable();

            $table->foreignId('set_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('auto_resume_at')->nullable();

            $table->unsignedInteger('max_calls_per_hour')->nullable();

            $table->decimal('daily_spend_cap_usd', 8, 2)->nullable();

            $table->decimal('current_daily_spend_usd', 8, 4)->default(0);

            $table->timestamp('spend_reset_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_overrides');
    }
};
