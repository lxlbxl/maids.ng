<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make a Flutterwave transfer bankable exactly once.
 *
 * Payments into the fixed virtual account carry no reference of ours, so
 * attribution is inferred (amount + account + window). That inference must not
 * be able to credit the same inbound transfer to two customers, and two
 * reconcile passes running concurrently must not both settle it. A unique index
 * on the claimed transaction id makes the database the arbiter instead of
 * application timing.
 *
 * Partial, because the column is an empty string on every historical row and
 * stays empty for any payment that was never matched to a Flutterwave
 * transaction (manual bank transfers, for one).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS matching_fee_payments_flw_tx_id_unique
            ON matching_fee_payments (flutterwave_tx_id)
            WHERE flutterwave_tx_id IS NOT NULL AND flutterwave_tx_id <> ''
        ");

        // Supports reconcileStatic's 'already claimed' lookup and the pending
        // sweep, both of which filter on the fixed account.
        DB::statement("
            CREATE INDEX IF NOT EXISTS matching_fee_payments_account_status_index
            ON matching_fee_payments (account_number, status)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS matching_fee_payments_flw_tx_id_unique');
        DB::statement('DROP INDEX IF EXISTS matching_fee_payments_account_status_index');
    }
};
