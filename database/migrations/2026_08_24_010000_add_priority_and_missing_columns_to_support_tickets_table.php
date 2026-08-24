<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('user_id');
            $table->text('message')->nullable()->after('subject');
            $table->string('priority')->default('medium')->after('status');
            $table->string('type')->nullable()->after('priority');
            $table->foreignId('cs_case_id')->nullable()->after('type')->constrained('cs_cases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['cs_case_id']);
            $table->dropColumn(['subject', 'message', 'priority', 'type', 'cs_case_id']);
        });
    }
};
