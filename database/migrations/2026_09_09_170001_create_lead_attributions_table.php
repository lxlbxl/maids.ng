<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone-keyed attribution stash. The bridge writes here the first time a person
 * messages after a tracked click (or from a CTWA ad); AgentApi\UserController@store
 * reads it by phone when Peace creates the employer, then it can be pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_attributions', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32);          // normalised digits, intl
            $table->json('attribution');          // {channel, campaign, source, utm_*, referrer, ...}
            $table->foreignId('wa_click_id')->nullable()->constrained('wa_clicks')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('phone');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_attributions');
    }
};
