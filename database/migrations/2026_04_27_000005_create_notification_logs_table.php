<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('user_type', 20); // 'employer' or 'maid'
            $table->string('channel', 20); // 'sms', 'email', 'push'
            $table->string('type', 50); // 'match', 'reminder', 'payment', 'acceptance', etc.
            $table->text('content');
            $table->json('context_json')->nullable()->comment('Full context for AI follow-ups');
            $table->timestamp('sent_at');
            $table->string('timezone', 50)->default('Africa/Lagos');
            $table->time('local_time_sent')->nullable();
            $table->string('delivery_status', 20)->default('pending'); // pending, delivered, failed
            $table->string('provider_response', 255)->nullable();
            $table->string('engagement', 20)->nullable(); // opened, clicked, replied
            $table->boolean('ai_generated')->default(false);
            $table->text('ai_reasoning')->nullable();
            $table->unsignedTinyInteger('follow_up_sequence')->default(1)->comment('1st, 2nd, 3rd reminder, etc.');
            $table->foreignId('parent_notification_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->foreignId('related_assignment_id')->nullable();
            $table->foreignId('related_preference_id')->nullable()->constrained('employer_preferences')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'user_type']);
            $table->index(['type', 'status']);
            $table->index('sent_at');
            $table->index('scheduled_at');
            $table->index(['related_assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
