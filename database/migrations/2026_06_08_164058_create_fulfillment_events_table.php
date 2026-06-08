<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('fulfillment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_case_id')->constrained('fulfillment_cases');
            $table->string('event_type');
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->text('notes')->nullable();
            $table->string('actor_type')->default('agent');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('fulfillment_events'); }
};
