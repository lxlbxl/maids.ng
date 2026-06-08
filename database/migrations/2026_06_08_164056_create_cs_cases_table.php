<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cs_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->nullable()->constrained('maid_assignments');
            $table->foreignId('employer_id')->constrained('users');
            $table->foreignId('maid_id')->nullable()->constrained('users');
            $table->string('health_status')->default('healthy');
            $table->integer('satisfaction_score')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_appraisal_due')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cs_cases'); }
};
