<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_messages', 'role')) {
                $table->string('role', 50)->default('user')->after('conversation_id');
            }
            if (!Schema::hasColumn('agent_messages', 'tool_call')) {
                $table->json('tool_call')->nullable()->after('content');
            }
            if (!Schema::hasColumn('agent_messages', 'external_message_id')) {
                $table->string('external_message_id', 500)->nullable()->after('tool_call');
            }
            if (!Schema::hasColumn('agent_messages', 'tokens_used')) {
                $table->unsignedInteger('tokens_used')->nullable()->after('external_message_id');
            }
            if (!Schema::hasColumn('agent_messages', 'admin_read')) {
                $table->boolean('admin_read')->default(false)->after('tokens_used');
            }

            // Drop old columns from run-migration.php if they exist
            if (Schema::hasColumn('agent_messages', 'direction')) {
                $table->dropColumn('direction');
            }
            if (Schema::hasColumn('agent_messages', 'metadata')) {
                $table->dropColumn('metadata');
            }


        });
    }

    public function down(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->dropColumn(['role', 'tool_call', 'external_message_id', 'tokens_used', 'admin_read']);
        });
    }
};
