<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_notes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type')->comment('fulfillment_case, cs_case, onboarding_journey, user, etc.');
            $table->unsignedBigInteger('entity_id');
            $table->text('note');
            $table->string('action_taken')->nullable()->comment('call_placed, message_sent, ticket_opened, stage_advanced, etc.');
            $table->string('outcome')->nullable()->comment('success, partial_success, failed, pending');
            $table->string('next_action')->nullable();
            $table->timestamp('next_action_due_at')->nullable();
            $table->string('agent_type')->nullable()->comment('Which agent wrote this');
            $table->unsignedBigInteger('agent_user_id')->nullable()->comment('admin/agent user who wrote it');
            $table->json('metadata')->nullable()->comment('Extra context JSON');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
            $table->index('agent_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_notes');
    }
};
