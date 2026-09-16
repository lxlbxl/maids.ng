<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound announcements for the helper group — matches made, placements started.
 *
 * Posting a result back to the group is social proof: helpers see that replying
 * to a posting actually leads to work, and that the person who got it was one of
 * them. Nothing else in the pipeline closes that loop; openings go out and
 * nothing ever comes back.
 *
 * Queued rather than posted inline because the app runs as www-data while the
 * group sender (Evolution API credentials, quiet-hours gate) runs as brewadmin.
 * A queue also gives dedupe, retry, and a natural hold during quiet hours —
 * a placement confirmed at 23:40 should be celebrated in the morning, not push
 * a notification to a few hundred people overnight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_announcements', function (Blueprint $table) {
            $table->id();

            // 'matched'  — helper accepted an opening
            // 'started'  — helper actually began work
            $table->string('event', 24);

            $table->string('job_code', 16)->nullable();
            $table->unsignedBigInteger('maid_user_id')->nullable();

            // The text is rendered at enqueue time and stored, so what was
            // approved is what gets sent, and a later profile edit cannot change
            // a message that is already in the queue.
            $table->text('body');

            $table->string('status', 16)->default('pending');   // pending | sent | failed | skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // One announcement per event per helper per opening — a retry of the
            // outcome call must not re-congratulate someone.
            $table->unique(['event', 'job_code', 'maid_user_id'], 'group_announcements_unique_event');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_announcements');
    }
};
