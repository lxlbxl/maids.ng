<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The permanent attribution record for a user. Written once when the account is
 * created (from a WhatsApp conversation via the agent-api, or a native site
 * signup). `first_touch` never changes; `last_touch` may be updated on a later
 * tracked visit. `channel` / `campaign` / `source` are denormalised for reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('first_touch');
            $table->json('last_touch')->nullable();
            $table->string('channel', 32)->nullable();
            $table->string('campaign', 190)->nullable();
            $table->string('source', 40)->nullable();
            $table->foreignId('wa_click_id')->nullable()->constrained('wa_clicks')->nullOnDelete();
            $table->timestamps();

            $table->index('channel');
            $table->index('campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_attributions');
    }
};
