<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers who put their hand up for a specific opening in the helper group.
 *
 * This existed only as a JSON file (group-rollout/job-registry.json) that
 * nothing in the app could read. maid-match queried every available maid and
 * ranked on location/NIN/experience, with no idea who had actually volunteered
 * for the job being filled — so the strongest signal in the pipeline was thrown
 * away. Eighteen claims across eleven openings produced zero placements.
 *
 * Bringing claims into the database lets the matcher surface volunteers first,
 * lets a job be closed once it is filled, and makes fill-rate measurable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_job_claims', function (Blueprint $table) {
            $table->id();

            $table->string('job_code', 16);                 // MNG-17
            $table->unsignedBigInteger('maid_user_id');     // users.id, role=maid
            $table->string('wa_id', 32)->nullable();

            // The opening, as posted. Denormalised on purpose: the group post is
            // the contract the helper responded to, and editing the source
            // afterwards must not silently change what she agreed to.
            $table->string('role')->nullable();
            $table->string('area')->nullable();
            $table->string('job_type')->nullable();

            // Employer linkage. employer_id is nullable because the registry
            // has never carried one — openings are tracked by Paperclip issue
            // (MAI-1359) and sometimes a bare WhatsApp number.
            $table->string('employer_issue', 32)->nullable();
            $table->unsignedBigInteger('employer_id')->nullable();

            // claimed -> shortlisted -> assigned, or rejected/withdrawn.
            $table->string('status', 24)->default('claimed');
            $table->string('reject_reason')->nullable();

            // Snapshot of the checks at claim time, so a shortlist can be built
            // without re-querying, and so we can see whether quality improves.
            $table->boolean('nin_verified')->default(false);
            $table->string('availability', 24)->nullable();
            $table->string('maid_city')->nullable();

            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            // One claim per helper per opening — she cannot volunteer twice.
            $table->unique(['job_code', 'maid_user_id']);
            $table->index(['job_code', 'status']);
            $table->index('maid_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_job_claims');
    }
};
