<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maid_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('maid_profiles', 'first_name')) {
                $table->string('first_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('maid_profiles', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maid_profiles', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
