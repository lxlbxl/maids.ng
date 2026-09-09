<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per tap of a WhatsApp CTA that routed through /wa/{source}. Holds the
 * first-touch attribution captured at that moment so the wa-paperclip-bridge can
 * reconstruct it from the short `code` carried in the prefilled message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('source', 40)->nullable();   // hero_primary | header | sticky_bar | ...
            $table->string('intent', 12)->default('hire'); // hire | work
            $table->string('channel', 32)->nullable();   // google_ads | organic_search | meta_paid | ...
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 190)->nullable();
            $table->string('utm_content', 190)->nullable();
            $table->string('utm_term', 190)->nullable();
            $table->string('gclid', 255)->nullable();
            $table->string('fbclid', 255)->nullable();
            $table->text('referrer')->nullable();
            $table->string('referring_domain', 190)->nullable();
            $table->string('landing_path', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('posthog_distinct_id', 190)->nullable();
            $table->foreignId('resolved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('channel');
            $table->index('created_at');
            $table->index('resolved_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_clicks');
    }
};
