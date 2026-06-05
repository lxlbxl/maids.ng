<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employer_preferences', function (Blueprint $table) {
            if (!Schema::hasColumn('employer_preferences', 'quiz_status')) {
                $table->enum('quiz_status', ['in_progress', 'completed', 'abandoned'])
                    ->default('in_progress')
                    ->after('id');
            }
            if (!Schema::hasColumn('employer_preferences', 'quiz_started_at')) {
                $table->timestamp('quiz_started_at')->nullable()->after('quiz_status');
            }
            if (!Schema::hasColumn('employer_preferences', 'quiz_completed_at')) {
                $table->timestamp('quiz_completed_at')->nullable()->after('quiz_started_at');
            }
            if (!Schema::hasColumn('employer_preferences', 'matches_shown_at')) {
                $table->timestamp('matches_shown_at')->nullable()->after('quiz_completed_at');
            }
            if (!Schema::hasColumn('employer_preferences', 'current_step')) {
                $table->unsignedTinyInteger('current_step')->default(1)->after('matches_shown_at');
            }

            $table->index(['quiz_status', 'quiz_started_at']);
            $table->index('matches_shown_at');
        });
    }

    public function down(): void
    {
        Schema::table('employer_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'quiz_status',
                'quiz_started_at',
                'quiz_completed_at',
                'matches_shown_at',
                'current_step',
            ]);
        });
    }
};