<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_channel_identities', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_channel_identities', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('external_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('agent_channel_identities', 'otp')) {
                $table->string('otp', 10)->nullable()->after('email');
            }
            if (!Schema::hasColumn('agent_channel_identities', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable()->after('otp');
            }
            if (!Schema::hasColumn('agent_channel_identities', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('otp_expires_at');
            }
            if (!Schema::hasColumn('agent_channel_identities', 'channel_meta')) {
                $table->json('channel_meta')->nullable()->after('is_verified');
            }
            if (!Schema::hasColumn('agent_channel_identities', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('channel_meta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_channel_identities', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'otp', 'otp_expires_at', 'is_verified', 'channel_meta', 'last_seen_at']);
        });
    }
};
