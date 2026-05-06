<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * User events table tracks user interactions for analytics:
     * - Page views
     * - Quiz starts/abandonments
     * - Match views
     * - Click events
     */
    public function up(): void
    {
        Schema::dropIfExists('user_events');
        Schema::create('user_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 64)->index();
            $table->string('event_type', 50)->index();
            // event_types: page_view, quiz_start, quiz_abandon, quiz_complete, 
            //              matches_viewed, match_clicked, booking_started, 
            //              payment_initiated, account_created, login, logout
            $table->string('page_url', 500)->nullable();
            $table->json('event_data')->nullable();
            // Flexible JSON payload: { quiz_id, match_id, duration_seconds, etc. }
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_events');
    }
};