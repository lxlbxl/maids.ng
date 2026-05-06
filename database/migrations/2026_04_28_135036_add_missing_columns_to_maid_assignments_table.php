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
        Schema::table('maid_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('maid_assignments', 'matching_fee_paid')) {
                $table->boolean('matching_fee_paid')->default(false)->after('status');
            }
            if (!Schema::hasColumn('maid_assignments', 'matching_fee_amount')) {
                $table->decimal('matching_fee_amount', 12, 2)->default(0)->after('matching_fee_paid');
            }
            if (!Schema::hasColumn('maid_assignments', 'guarantee_match')) {
                $table->boolean('guarantee_match')->default(false)->after('matching_fee_amount');
            }
            if (!Schema::hasColumn('maid_assignments', 'guarantee_period_days')) {
                $table->integer('guarantee_period_days')->nullable()->after('guarantee_match');
            }
            if (!Schema::hasColumn('maid_assignments', 'ai_match_reasoning')) {
                $table->json('ai_match_reasoning')->nullable()->after('ai_recommendation_reason');
            }
            if (!Schema::hasColumn('maid_assignments', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('ai_match_reasoning');
            }
            if (!Schema::hasColumn('maid_assignments', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('maid_assignments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('maid_assignments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('maid_assignments', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->after('cancellation_reason');
            }
            if (!Schema::hasColumn('maid_assignments', 'salary_amount')) {
                $table->decimal('salary_amount', 12, 2)->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('maid_assignments', 'salary_currency')) {
                $table->string('salary_currency', 10)->default('NGN')->after('salary_amount');
            }
            if (!Schema::hasColumn('maid_assignments', 'job_location')) {
                $table->string('job_location')->nullable()->after('salary_currency');
            }
            if (!Schema::hasColumn('maid_assignments', 'job_type')) {
                $table->string('job_type')->nullable()->after('job_location');
            }
            if (!Schema::hasColumn('maid_assignments', 'special_requirements')) {
                $table->json('special_requirements')->nullable()->after('job_type');
            }
            if (!Schema::hasColumn('maid_assignments', 'notes')) {
                $table->text('notes')->nullable()->after('special_requirements');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maid_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'matching_fee_paid',
                'matching_fee_amount',
                'guarantee_match',
                'guarantee_period_days',
                'ai_match_reasoning',
                'started_at',
                'completed_at',
                'cancelled_at',
                'cancellation_reason',
                'cancelled_by',
                'salary_amount',
                'salary_currency',
                'job_location',
                'job_type',
                'special_requirements',
                'notes',
            ]);
        });
    }
};
