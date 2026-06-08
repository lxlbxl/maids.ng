<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('fulfillment_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users');
            $table->foreignId('maid_id')->nullable()->constrained('users');
            $table->foreignId('preference_id')->nullable()->constrained('employer_preferences');
            $table->foreignId('assignment_id')->nullable()->constrained('maid_assignments');
            $table->string('stage')->default('payment_confirmed');
            $table->string('status')->default('active');
            $table->decimal('agreed_salary', 12, 2)->nullable();
            $table->integer('maid_salary')->nullable();
            $table->integer('employer_salary')->nullable();
            $table->timestamp('salary_confirmed_at')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->string('start_time')->nullable();
            $table->text('employer_address')->nullable();
            $table->boolean('maid_arrived_day_one')->nullable();
            $table->timestamp('day_one_confirmed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->string('replacement_status')->nullable();
            $table->text('fail_reason')->nullable();
            $table->integer('hours_in_stage')->default(0);
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_action_due_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('fulfillment_cases'); }
};
