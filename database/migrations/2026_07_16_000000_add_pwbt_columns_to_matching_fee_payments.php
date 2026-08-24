<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('matching_fee_payments', 'tx_ref')) {
                $table->string('tx_ref')->nullable()->after('reference')->unique();
            }
            if (!Schema::hasColumn('matching_fee_payments', 'account_number')) {
                $table->string('account_number')->nullable()->after('tx_ref');
            }
            if (!Schema::hasColumn('matching_fee_payments', 'account_bank')) {
                $table->string('account_bank')->nullable()->after('account_number');
            }
            if (!Schema::hasColumn('matching_fee_payments', 'account_name')) {
                $table->string('account_name')->nullable()->after('account_bank');
            }
            if (!Schema::hasColumn('matching_fee_payments', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('account_name');
            }
            if (!Schema::hasColumn('matching_fee_payments', 'flutterwave_tx_id')) {
                $table->string('flutterwave_tx_id')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            $table->dropColumn([
                'tx_ref', 'account_number', 'account_bank', 'account_name',
                'expires_at', 'flutterwave_tx_id',
            ]);
        });
    }
};
