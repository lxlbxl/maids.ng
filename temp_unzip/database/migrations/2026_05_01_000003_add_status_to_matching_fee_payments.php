<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            // If status does not already exist as a proper enum, add/modify it
            if (!Schema::hasColumn('matching_fee_payments', 'status')) {
                $table->enum('status', ['pending', 'successful', 'failed', 'refunded'])
                    ->default('pending')
                    ->after('paystack_reference');
            }

            // When payment was confirmed by webhook
            if (!Schema::hasColumn('matching_fee_payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }

            // Payment type — needed for the Treasurer agent and outreach context
            if (!Schema::hasColumn('matching_fee_payments', 'payment_type')) {
                $table->enum('payment_type', ['matching_fee', 'premium_matching', 'renewal'])
                    ->default('matching_fee')
                    ->after('paid_at');
            }

            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'payment_type']);
        });
    }
};