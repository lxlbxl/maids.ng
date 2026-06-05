<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('salary_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_schedule_id')->constrained('salary_schedules')->onDelete('cascade');
            $table->string('reminder_type', 50); // 'upcoming', 'overdue', 'escalated'
            $table->timestamp('sent_to_employer_at')->nullable();
            $table->string('employer_response', 255)->nullable(); // 'will_pay', 'needs_extension', 'disputed', etc.
            $table->timestamp('escalated_to_admin_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedTinyInteger('reminder_sequence')->default(1)->comment('1st, 2nd, 3rd reminder');
            $table->json('context_json')->nullable()->comment('Context for AI follow-ups');
            $table->timestamps();

            $table->index('salary_schedule_id');
            $table->index('reminder_type');
            $table->index('sent_to_employer_at');
            $table->index('escalated_to_admin_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_reminders');
    }
};
