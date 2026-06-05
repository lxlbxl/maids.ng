<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maid_assignments', function (Blueprint $table) {
            $columns = [
                ['matching_fee_paid', function (Blueprint $t) { $t->boolean('matching_fee_paid')->default(false); }],
                ['matching_fee_amount', function (Blueprint $t) { $t->decimal('matching_fee_amount', 10, 2)->default(0); }],
                ['guarantee_match', function (Blueprint $t) { $t->boolean('guarantee_match')->default(false); }],
                ['guarantee_period_days', function (Blueprint $t) { $t->integer('guarantee_period_days')->default(90); }],
                ['salary_amount', function (Blueprint $t) { $t->decimal('salary_amount', 12, 2)->nullable(); }],
                ['salary_currency', function (Blueprint $t) { $t->string('salary_currency', 3)->default('NGN'); }],
                ['job_location', function (Blueprint $t) { $t->string('job_location')->nullable(); }],
                ['job_type', function (Blueprint $t) { $t->string('job_type')->nullable(); }],
                ['special_requirements', function (Blueprint $t) { $t->json('special_requirements')->nullable(); }],
                ['notes', function (Blueprint $t) { $t->text('notes')->nullable(); }],
                ['started_at', function (Blueprint $t) { $t->timestamp('started_at')->nullable(); }],
                ['completed_at', function (Blueprint $t) { $t->timestamp('completed_at')->nullable(); }],
                ['cancelled_at', function (Blueprint $t) { $t->timestamp('cancelled_at')->nullable(); }],
                ['cancellation_reason', function (Blueprint $t) { $t->text('cancellation_reason')->nullable(); }],
                ['employer_responded_at', function (Blueprint $t) { $t->timestamp('employer_responded_at')->nullable(); }],
                ['reminder_sent', function (Blueprint $t) { $t->boolean('reminder_sent')->default(false); }],
                ['ended_at', function (Blueprint $t) { $t->timestamp('ended_at')->nullable(); }],
                ['response_deadline', function (Blueprint $t) { $t->timestamp('response_deadline')->nullable(); }],
                ['context_json', function (Blueprint $t) { $t->json('context_json')->nullable(); }],
                ['assigned_by_type', function (Blueprint $t) { $t->string('assigned_by_type', 20)->nullable(); }],
                ['refund_amount', function (Blueprint $t) { $t->decimal('refund_amount', 10, 2)->nullable(); }],
                ['refund_transaction_id', function (Blueprint $t) { $t->foreignId('refund_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete(); }],
            ];

            foreach ($columns as [$name, $definition]) {
                if (!Schema::hasColumn('maid_assignments', $name)) {
                    $definition($table);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('maid_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'matching_fee_paid', 'matching_fee_amount', 'guarantee_match', 'guarantee_period_days',
                'salary_amount', 'salary_currency', 'job_location', 'job_type', 'special_requirements',
                'notes', 'started_at', 'completed_at', 'cancelled_at', 'cancellation_reason',
                'employer_responded_at', 'reminder_sent', 'ended_at', 'response_deadline',
                'context_json', 'assigned_by_type', 'refund_amount', 'refund_transaction_id',
            ]);
        });
    }
};
