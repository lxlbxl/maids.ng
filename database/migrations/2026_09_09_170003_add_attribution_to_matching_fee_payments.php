<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the employer's attribution at the moment the matching fee is paid,
 * so revenue can be grouped by channel / campaign without a join that could
 * shift if the user_attributions row is ever updated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            $table->json('attribution')->nullable()->after('gateway_response');
        });
    }

    public function down(): void
    {
        Schema::table('matching_fee_payments', function (Blueprint $table) {
            $table->dropColumn('attribution');
        });
    }
};
