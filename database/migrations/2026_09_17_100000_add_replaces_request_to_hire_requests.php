<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a guarantee replacement to the request it replaces.
 *
 * The 10-day guarantee means a helper who does not work out is replaced free.
 * That replacement is not a new sale, but it was being modelled as a separate
 * unpaid request — so once helper reservations were restricted to paying
 * customers, the household we had ALREADY let down lost its backup cover, while
 * a household whose first placement went fine kept theirs. Exactly backwards.
 *
 * A request that replaces another inherits its paid status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hire_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('replaces_request_id')->nullable()->after('preference_id');
            $table->index('replaces_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('hire_requests', fn (Blueprint $t) => $t->dropColumn('replaces_request_id'));
    }
};
