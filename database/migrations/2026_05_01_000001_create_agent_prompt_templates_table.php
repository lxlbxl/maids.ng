<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_prompt_templates', function (Blueprint $table) {
            $table->id();

            // Which agent this template belongs to.
            // Must match the agent's self-identified name string exactly.
            // Known values: ambassador, scout, sentinel, referee,
            //               concierge, treasurer, gatekeeper
            $table->string('agent_name', 50)->index();

            // User tier this template is designed for.
            // guest = not logged in (web), not verified (external channel)
            // lead  = external channel user, phone known but not linked to account
            // authenticated = verified member on any channel
            // admin = internal admin use
            $table->enum('tier', ['guest', 'lead', 'authenticated', 'admin'])
                ->default('guest');

            // Human-readable label for the admin UI.
            // Example: "Ambassador - Guest Tier v2"
            $table->string('label', 150);

            // The full system prompt text.
            // Uses {{PLACEHOLDER}} tokens that KnowledgeService will replace.
            // Available tokens: {{AGENT_NAME}}, {{BUSINESS_NAME}},
            //                   {{MATCHING_FEE}}, {{COMMISSION_RATE}},
            //                   {{GUARANTEE_DAYS}}, {{CURRENT_DATE}}
            $table->longText('system_prompt');

            // Version counter. Incremented automatically on each save.
            // Allows rollback via is_active toggle.
            $table->unsignedSmallInteger('version')->default(1);

            // Only one template per agent+tier combination can be active at once.
            // KnowledgeService will throw if zero active templates found for a request.
            $table->boolean('is_active')->default(true)->index();

            // Audit: who last changed this template.
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Store the previous version's prompt for instant rollback.
            $table->longText('previous_prompt')->nullable();

            $table->timestamps();

            // Enforce: only one active template per agent+tier
            $table->unique(['agent_name', 'tier', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_prompt_templates');
    }
};