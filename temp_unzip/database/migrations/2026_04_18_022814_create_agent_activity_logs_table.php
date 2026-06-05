<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('agent_name'); // Scout, Gatekeeper, etc.
            $table->string('action'); // e.g. "scored_match", "verified_nin"
            $table->string('subject_type')->nullable(); // Class name
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('decision'); // e.g. "approved", "rejected", "scored"
            $table->integer('confidence_score')->default(100); // 0-100
            $table->text('reasoning')->nullable(); // Why the agent did it
            $table->boolean('requires_review')->default(false); // Escalation flag
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('overridden')->default(false);
            $table->text('override_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_activity_logs');
    }
};
