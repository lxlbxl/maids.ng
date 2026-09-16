<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store Flutterwave's own transfer reference, and allow a payment to be cancelled.
 *
 * flw_ref is the NIBSS session id for a bank transfer — the same string the
 * customer can read off their bank receipt ("Session ID" on an OPay slip). That
 * makes it the one identifier shared by us, Flutterwave and the payer, so it is
 * the correct key to reconcile and de-duplicate on. Our own reference never was:
 * agents re-wrapped it as OPAY_x, RECON_OPAY_x and x for the same transfer.
 *
 * 'cancelled' is added to the status CHECK because the pending rows left over
 * from the dynamic-account era are neither paid nor failed — no transfer was
 * ever attempted against most of them. They were offers that expired.
 *
 * Note the existing constraint permits only pending/paid/failed/refunded, which
 * is independent proof that the 'completed' status the old status() endpoint
 * filtered on could never have been written to this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('matching_fee_payments', 'flw_ref')) {
                $table->string('flw_ref')->nullable()->after('flutterwave_tx_id');
            }
            if (!Schema::hasColumn('matching_fee_payments', 'cancelled_reason')) {
                $table->string('cancelled_reason')->nullable()->after('flw_ref');
            }
        });

        // One inbound transfer settles at most one payment.
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS matching_fee_payments_flw_ref_unique
            ON matching_fee_payments (flw_ref)
            WHERE flw_ref IS NOT NULL AND flw_ref <> ''
        ");

        DB::statement('ALTER TABLE matching_fee_payments DROP CONSTRAINT IF EXISTS matching_fee_payments_status_check');
        DB::statement("
            ALTER TABLE matching_fee_payments
            ADD CONSTRAINT matching_fee_payments_status_check
            CHECK (status::text = ANY (ARRAY['pending','paid','failed','refunded','cancelled']::text[]))
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS matching_fee_payments_flw_ref_unique');
        DB::statement('ALTER TABLE matching_fee_payments DROP CONSTRAINT IF EXISTS matching_fee_payments_status_check');
        DB::statement("
            ALTER TABLE matching_fee_payments
            ADD CONSTRAINT matching_fee_payments_status_check
            CHECK (status::text = ANY (ARRAY['pending','paid','failed','refunded']::text[]))
        ");
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            $table->dropColumn(['flw_ref', 'cancelled_reason']);
        });
    }
};
