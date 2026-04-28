<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maid_assignments', function (Blueprint $table) {
            $table->boolean('matching_fee_paid')->default(false)->after('status');
            $table->decimal('matching_fee_amount', 12, 2)->default(0)->after('matching_fee_paid');
            $table->boolean('guarantee_match')->default(false)->after('matching_fee_amount');
            $table->integer('guarantee_period_days')->nullable()->after('guarantee_match');
            $table->json('ai_match_reasoning')->nullable()->after('ai_recommendation_reason');
            $table->timestamp('started_at')->nullable()->after('ai_match_reasoning');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->after('cancellation_reason');
            $table->decimal('salary_amount', 12, 2)->nullable()->after('cancelled_by');
            $table->string('salary_currency', 10)->default('NGN')->after('salary_amount');
            $table->string('job_location')->nullable()->after('salary_currency');
            $table->string('job_type')->nullable()->after('job_location');
            $table->json('special_requirements')->nullable()->after('job_type');
            $table->text('notes')->nullable()->after('special_requirements');
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
