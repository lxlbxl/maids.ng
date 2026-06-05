<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_knowledge_base', function (Blueprint $table) {
            $table->id();

            // Categorises the article for filtering and UI grouping.
            $table->enum('category', [
                'policy',       // Business rules, guarantees, terms
                'faq',          // Common questions and answers
                'procedure',    // Step-by-step how-tos
                'legal',        // Legal disclaimers, data usage
                'restriction',  // What the agent must never do or say
                'onboarding',   // How the platform works for new users
                'pricing',      // Supplementary pricing context (NOT the source of truth — settings are)
            ])->index();

            // Short title shown in admin list and injected as a heading in context.
            $table->string('title', 200);

            // The full article text. Plain text or simple markdown.
            // Do NOT use HTML — LLMs handle markdown fine.
            $table->longText('content');

            // JSON array of agent names this article is injected into.
            // Use ["all"] to inject into every agent.
            // Use ["ambassador"] for ambassador only.
            // Use ["ambassador","scout"] for multiple specific agents.
            // Valid values: all, ambassador, scout, sentinel, referee,
            //               concierge, treasurer, gatekeeper
            $table->json('applies_to')->default('["all"]');

            // JSON array of tiers this article is visible for.
            // Use ["all"] to apply to every tier.
            // Use ["authenticated"] to restrict to logged-in users only.
            // This allows hiding sensitive info from guest-facing context.
            $table->json('visible_to_tiers')->default('["all"]');

            // Controls injection order. Lower number = injected earlier (more prominent).
            // Articles with priority 1-10 appear before those with 50-100.
            // Restrictions should be priority 1-5 (always first).
            // FAQs can be 50+.
            $table->unsignedSmallInteger('priority')->default(50)->index();

            // Soft toggle. Inactive articles are never injected.
            $table->boolean('is_active')->default(true)->index();

            // Audit fields
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_knowledge_base');
    }
};