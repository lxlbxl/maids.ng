<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ranked queue of candidates per opening: one primary, and backups behind her.
 *
 * Two failures this fixes.
 *
 * Nothing excluded a maid who was already placed, so the same top-ranked
 * helpers were offered to every customer — six of the seven maids holding an
 * active assignment were still in the offerable pool, Onyinyechi among them
 * while she was already due to start at another family on 22 September. Being
 * queued or offered here is what marks a helper as spoken for.
 *
 * And when a helper stopped replying there was no second name, so the family
 * went back to the start of the process. A rank-ordered queue means the next
 * candidate is already chosen, already screened, and can be approached the same
 * day.
 *
 * Candidates sourced from the helper group (she replied "AVAILABLE MNG-17") are
 * ranked above matcher suggestions: she has already said she wants this job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_candidates', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employer_id');
            $table->unsignedBigInteger('preference_id')->nullable();
            $table->string('job_code', 16)->nullable();          // MNG-17, when the opening came from the group
            $table->unsignedBigInteger('maid_user_id');          // users.id, role=maid — never maid_profiles.id

            // 1 = who we are approaching now; 2+ = backups in order.
            $table->unsignedSmallInteger('rank');

            // queued    — chosen, not yet contacted
            // offered   — presented to the family or the helper approached
            // accepted  — she agreed; an assignment should exist
            // declined  — she said no
            // unreachable — no reply within the follow-up window
            // placed    — started work
            // superseded — the opening was filled by someone else
            $table->string('status', 16)->default('queued');

            // 'group_claim' outranks 'matcher' — see the class docblock.
            $table->string('source', 16)->default('matcher');

            $table->timestamp('offered_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('outcome_reason')->nullable();
            $table->timestamps();

            // A helper appears once per opening. Without this the agent can
            // re-queue the same person on every run, which is how the same
            // names kept resurfacing.
            $table->unique(['employer_id', 'preference_id', 'maid_user_id'], 'placement_candidates_unique_per_opening');

            $table->index(['employer_id', 'status']);
            $table->index(['maid_user_id', 'status']);
            $table->index(['job_code', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_candidates');
    }
};
