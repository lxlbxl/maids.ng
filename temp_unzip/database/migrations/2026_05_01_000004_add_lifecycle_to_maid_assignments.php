<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maid_assignments', function (Blueprint $table) {
            // Start date — when the maid actually begins work
            if (!Schema::hasColumn('maid_assignments', 'start_date')) {
                $table->date('start_date')->nullable()->after('status');
            }

            // End date — when the assignment ends (termination or completion)
            if (!Schema::hasColumn('maid_assignments', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }

            // Termination reason — why the assignment ended
            if (!Schema::hasColumn('maid_assignments', 'termination_reason')) {
                $table->string('termination_reason')->nullable()->after('end_date');
            }

            // Employer satisfaction score (1-5)
            if (!Schema::hasColumn('maid_assignments', 'satisfaction_score')) {
                $table->unsignedTinyInteger('satisfaction_score')->nullable()->after('termination_reason');
            }

            // Whether the employer left a review
            if (!Schema::hasColumn('maid_assignments', 'review_submitted')) {
                $table->boolean('review_submitted')->default(false)->after('satisfaction_score');
            }

            $table->index(['start_date', 'end_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('maid_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'start_date',
                'end_date',
                'termination_reason',
                'satisfaction_score',
                'review_submitted',
            ]);
        });
    }
};