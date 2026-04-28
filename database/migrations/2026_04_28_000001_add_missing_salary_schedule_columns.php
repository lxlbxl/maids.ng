<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('salary_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_schedules', 'reminder_3_days_sent')) {
                $table->boolean('reminder_3_days_sent')->default(false)->after('last_reminder_sent_at');
            }
            if (!Schema::hasColumn('salary_schedules', 'reminder_1_day_sent')) {
                $table->boolean('reminder_1_day_sent')->default(false)->after('reminder_3_days_sent');
            }
            if (!Schema::hasColumn('salary_schedules', 'reminder_due_sent')) {
                $table->boolean('reminder_due_sent')->default(false)->after('reminder_1_day_sent');
            }
            if (!Schema::hasColumn('salary_schedules', 'escrow_funded')) {
                $table->boolean('escrow_funded')->default(false)->after('escrow_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_schedules', function (Blueprint $table) {
            $table->dropColumn(['reminder_3_days_sent', 'reminder_1_day_sent', 'reminder_due_sent', 'escrow_funded']);
        });
    }
};
