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
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();

            // References
            $table->foreignId('salary_schedule_id')->constrained('salary_schedules')->onDelete('cascade');
            $table->foreignId('assignment_id')->constrained('maid_assignments')->onDelete('cascade');
            $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('maid_id')->constrained('users')->onDelete('cascade');

            // Payment period
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->date('due_date');
            $table->date('paid_date')->nullable();

            // Payment amounts
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->json('deduction_breakdown')->nullable(); // Agency fees, taxes, etc.

            // Payment status
            $table->enum('status', [
                'pending',           // Awaiting employer payment
                'employer_paid',     // Employer paid to platform
                'processing',        // Platform processing
                'paid_to_maid',      // Transferred to maid wallet
                'failed',            // Payment failed
                'disputed',          // Under dispute
                'refunded'           // Refunded to employer
            ])->default('pending');

            // Payment method tracking
            $table->string('employer_payment_method')->nullable(); // wallet, card, bank_transfer
            $table->string('employer_payment_reference')->nullable();
            $table->timestamp('employer_paid_at')->nullable();

            // Maid receipt tracking
            $table->string('maid_payment_method')->nullable(); // wallet, bank_transfer
            $table->string('maid_payment_reference')->nullable();
            $table->timestamp('maid_paid_at')->nullable();

            // Wallet transaction references
            $table->foreignId('employer_wallet_txn_id')->nullable()->constrained('wallet_transactions')->onDelete('set null');
            $table->foreignId('maid_wallet_txn_id')->nullable()->constrained('wallet_transactions')->onDelete('set null');

            // Reminder tracking
            $table->integer('reminder_count')->default(0);
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamp('final_notice_sent_at')->nullable();

            // AI/Automation tracking
            $table->boolean('auto_processed')->default(false);
            $table->timestamp('auto_processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null'); // Admin if manual

            // Dispute handling
            $table->text('dispute_reason')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->foreignId('dispute_resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('dispute_resolved_at')->nullable();
            $table->text('dispute_resolution')->nullable();

            // Receipt and documentation
            $table->string('receipt_number')->unique()->nullable();
            $table->string('receipt_url')->nullable();
            $table->json('payment_proof')->nullable(); // URLs to proof of payment docs

            // Notes
            $table->text('employer_notes')->nullable();
            $table->text('maid_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['employer_id', 'status']);
            $table->index(['maid_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index('salary_schedule_id');
            $table->index('receipt_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
