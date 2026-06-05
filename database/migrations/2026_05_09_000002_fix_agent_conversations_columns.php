<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_conversations', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('channel_identity_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('agent_conversations', 'channel')) {
                $table->string('channel', 50)->default('web')->after('user_id');
            }
            if (!Schema::hasColumn('agent_conversations', 'intent_summary')) {
                $table->string('intent_summary', 500)->nullable()->after('status');
            }
            if (!Schema::hasColumn('agent_conversations', 'email_subject')) {
                $table->string('email_subject', 500)->nullable()->after('intent_summary');
            }
            if (!Schema::hasColumn('agent_conversations', 'email_thread_id')) {
                $table->string('email_thread_id', 500)->nullable()->after('email_subject');
            }
            if (!Schema::hasColumn('agent_conversations', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('email_thread_id');
            }
            if (!Schema::hasColumn('agent_conversations', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('admin_note')->constrained('users')->nullOnDelete();
            }


        });
    }

    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'channel', 'intent_summary', 'email_subject', 'email_thread_id', 'admin_note', 'assigned_to']);
        });
    }
};
