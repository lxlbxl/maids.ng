<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('agent_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'tool', 'system', 'admin']);
            $table->longText('content');
            $table->json('tool_call')->nullable();
            $table->string('external_message_id', 500)->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->boolean('admin_read')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
            $table->index('external_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};