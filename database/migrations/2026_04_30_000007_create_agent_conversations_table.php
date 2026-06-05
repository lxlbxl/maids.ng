<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_identity_id')->constrained('agent_channel_identities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('channel', ['web', 'email', 'whatsapp', 'instagram', 'facebook']);
            $table->enum('status', ['open', 'resolved', 'escalated', 'converted', 'spam'])->default('open');
            $table->string('intent_summary', 500)->nullable();
            $table->string('email_subject', 500)->nullable();
            $table->string('email_thread_id', 500)->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['channel_identity_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['channel', 'status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversations');
    }
};