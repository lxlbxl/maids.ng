<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('onboarding_touchpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained('onboarding_journeys');
            $table->foreignId('user_id')->constrained('users');
            $table->string('touchpoint_type');
            $table->string('channel')->nullable();
            $table->string('status')->default('sent');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('onboarding_touchpoints'); }
};
