<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_leads', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('channel_identity_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('agent_leads', 'name')) {
                $table->string('name', 255)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('agent_leads', 'phone')) {
                $table->string('phone', 30)->nullable()->after('name');
            }
            if (!Schema::hasColumn('agent_leads', 'email')) {
                $table->string('email', 255)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('agent_leads', 'intent')) {
                $table->json('intent')->nullable()->after('email');
            }


        });
    }

    public function down(): void
    {
        Schema::table('agent_leads', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'name', 'phone', 'email', 'intent']);
        });
    }
};
