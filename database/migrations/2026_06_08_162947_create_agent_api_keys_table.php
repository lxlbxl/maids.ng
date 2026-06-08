<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Human-readable label e.g. "n8n WhatsApp Agent"');
            $table->string('key', 64)->unique()->comment('Token prefix + hash: mng_sk_{64chars}');
            $table->json('scopes')->nullable()->comment('Array of permission scopes');
            $table->string('agent_type')->nullable()->comment('onboarding, fulfillment, sales, cs, ceo');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('agent_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_api_keys');
    }
};
