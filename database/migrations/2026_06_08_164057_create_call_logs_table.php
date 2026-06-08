<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('vapi_call_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('phone')->nullable();
            $table->string('call_type')->nullable();
            $table->string('status')->default('queued');
            $table->integer('duration_seconds')->nullable();
            $table->text('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->boolean('goal_achieved')->nullable();
            $table->text('notes')->nullable();
            $table->string('follow_up_action')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('call_logs'); }
};
