<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A hire request: one household asking for one helper.
 *
 * Until now the unit of work was implicit. An employer_preference stood in for
 * "what they want", and payments, assignments and fulfillment cases each hung
 * off it or off the employer directly — inconsistently. That worked while every
 * household hired exactly one helper, and broke the moment they did not: one
 * employer (MAI-1209) wants three housekeepers, MNG-7 is a family needing two
 * nannies. With no request to attach things to, "has this employer paid?" was
 * the only question the system could answer, so the second helper came out free
 * and the second placement was blocked as a duplicate.
 *
 * Making the request first-class answers the question that actually matters:
 * has THIS request been paid for, matched, and filled. An employer may hold
 * several at once, each with its own fee, its own candidate queue, its own
 * placement and its own 10-day guarantee.
 *
 * A request is done when the helper is confirmed resumed — not when she is
 * matched, and not when the money lands. Those are promises; resumption is the
 * outcome the family is paying for.
 *
 * employer_preferences is kept as the detail of what was asked for (quiz
 * answers, budget, schedule); hire_requests is the thing with a lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hire_requests', function (Blueprint $table) {
            $table->id();

            // Human-quotable in a WhatsApp thread or an issue title.
            $table->string('reference', 24)->unique();

            $table->unsignedBigInteger('employer_id');
            $table->unsignedBigInteger('preference_id')->nullable();

            // What was asked for, denormalised: the request is the contract, and
            // editing a preference later must not rewrite what was agreed.
            $table->string('role')->nullable();            // housekeeper, nanny, cook…
            $table->string('area')->nullable();
            $table->string('live_arrangement', 24)->nullable();   // live_in | live_out
            $table->text('details')->nullable();
            $table->string('job_code', 16)->nullable();    // MNG-17, if posted to the group

            // open      — created, fee not yet received
            // paid      — fee received, ready to match
            // matching  — candidates queued/offered
            // matched   — a helper accepted
            // fulfilled — helper confirmed resumed. This is "done".
            // cancelled — withdrawn or abandoned
            $table->string('status', 16)->default('open');

            $table->unsignedInteger('fee_amount')->default(20000);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason')->nullable();

            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->unsignedBigInteger('fulfillment_case_id')->nullable();
            $table->unsignedBigInteger('maid_user_id')->nullable();

            $table->timestamps();

            $table->index(['employer_id', 'status']);
            $table->index('status');
            $table->index('job_code');
        });

        // Everything that belongs to a request now says so. Nullable so the
        // backfill can run behind live traffic without a write freeze.
        foreach ([
            'matching_fee_payments',
            'maid_assignments',
            'fulfillment_cases',
            'placement_candidates',
        ] as $t) {
            if (Schema::hasTable($t) && !Schema::hasColumn($t, 'hire_request_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unsignedBigInteger('hire_request_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'matching_fee_payments',
            'maid_assignments',
            'fulfillment_cases',
            'placement_candidates',
        ] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'hire_request_id')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn('hire_request_id'));
            }
        }
        Schema::dropIfExists('hire_requests');
    }
};
