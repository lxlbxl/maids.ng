<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = Schema::hasTable('maid_profiles') ? 'maid_profiles' : 'users';

        Schema::table($table, function (Blueprint $table) {
            if (!Schema::hasColumn($table->getTable() ?? 'users', 'profile_completeness')) {
                $table->unsignedTinyInteger('profile_completeness')->default(0)->after('id');
            }
            if (!Schema::hasColumn($table->getTable() ?? 'users', 'is_profile_complete')) {
                $table->boolean('is_profile_complete')->default(false);
            }
            if (!Schema::hasColumn($table->getTable() ?? 'users', 'profile_completed_at')) {
                $table->timestamp('profile_completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Conservative — do not drop columns that may have been pre-existing
    }
};