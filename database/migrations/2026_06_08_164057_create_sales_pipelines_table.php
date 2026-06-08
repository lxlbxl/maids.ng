<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('funnel_stage')->default('awareness');
            $table->integer('lead_score')->default(0);
            $table->json('actions_taken')->nullable();
            $table->timestamp('last_outreach_at')->nullable();
            $table->integer('outreach_count')->default(0);
            $table->string('outreach_channel')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sales_pipelines'); }
};
