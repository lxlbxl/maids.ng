<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timing for the matching cadence.
 *
 * Every step of a match currently waits on someone remembering to chase it. A
 * helper who goes quiet stalls a request indefinitely: Tafa's first helper never
 * showed up and the search only restarted when a human noticed. Deadlines make
 * the waiting explicit, so a request either moves forward or hands itself to the
 * next candidate on its own.
 *
 * Two clocks, because they measure different things:
 *   claims_close_at — how long we hold an opening open for the group to answer.
 *                     Helpers reply in their own time; closing too early wastes
 *                     the volunteers, closing too late strands the family.
 *   respond_by      — how long one candidate gets before we follow up, and then
 *                     before we move on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hire_requests', function (Blueprint $table) {
            $table->timestamp('group_posted_at')->nullable()->after('job_code');
            $table->timestamp('claims_close_at')->nullable()->after('group_posted_at');
            $table->timestamp('queue_built_at')->nullable()->after('claims_close_at');
        });

        Schema::table('placement_candidates', function (Blueprint $table) {
            // When this candidate's silence becomes an answer.
            $table->timestamp('respond_by')->nullable()->after('offered_at');
            $table->timestamp('followed_up_at')->nullable()->after('respond_by');
        });
    }

    public function down(): void
    {
        Schema::table('hire_requests', fn (Blueprint $t) =>
            $t->dropColumn(['group_posted_at', 'claims_close_at', 'queue_built_at']));
        Schema::table('placement_candidates', fn (Blueprint $t) =>
            $t->dropColumn(['respond_by', 'followed_up_at']));
    }
};
