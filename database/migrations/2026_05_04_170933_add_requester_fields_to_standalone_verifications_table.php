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
        Schema::table('standalone_verifications', function (Blueprint $table) {
            $table->string('requester_name')->nullable()->after('requester_id');
            $table->string('requester_email')->nullable()->after('requester_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('standalone_verifications', function (Blueprint $table) {
            $table->dropColumn(['requester_name', 'requester_email']);
        });
    }
};