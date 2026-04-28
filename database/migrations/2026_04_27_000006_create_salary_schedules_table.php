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
        Schema::create('salary_schedules', function (Blueprint $table) {
            $table->id();

            // Assignment reference
            $table->foreignId('assignment_id')->constrained('maid_assignments')->onDelete('cascade');
            $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('maid_id')->constrained('users')->onDelete('cascade');

            // Salary configuration
            $table->decimal('monthly_salary', 12, 2);
            $table->integer('salary_day')->default(28); // Day of month salary is due (1-31)
            $table->date('employment_start_date');
            $table->date('first_salary_date'); // Calculated based on start date

            // Schedule tracking
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->date('next_salary_due_date');

            // Reminder configuration
            $table->integer('reminder_days_before')->default(3); // Days before due date to remind
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamp('next_reminder_scheduled_at')->nullable();

            // Payment status tracking
            $table->enum('payment_status', [
                'pending',           // Salary not yet due
                'reminder_sent',     // Reminder sent to employer
                'payment_initiated', // Employer initiated payment
                'paid',              // Salary paid to maid
                'overdue',           // Past due date, not paid
                'disputed'           // Payment disputed
            ])->default('pending');

            // Escrow tracking for salary
            $table->decimal('escrow_amount', 12, 2)->default(0);
            $table->timestamp('escrow_funded_at')->nullable();

            // AI/Automation tracking
            $table->integer('reminder_count')->default(0);
            $table->integer('escalation_level')->default(0); // 0=normal, 1=supervisor, 2=admin
            $table->timestamp('last_escalation_at')->nullable();

            // Metadata
            $table->json('salary_breakdown')->nullable(); // Base, allowances, deductions
            $table->text('special_notes')->nullable();
            $table->boolean('is_active')->default(true);

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['employer_id', 'payment_status']);
            $table->index(['maid_id', 'payment_status']);
            $table->index('next_salary_due_date');
            $table->index('next_reminder_scheduled_at');
            $table->index(['is_active', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_schedules');
    }
};
