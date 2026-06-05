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
        Schema::create('standalone_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            
            // Maid details being verified
            $table->string('maid_nin');
            $table->string('maid_first_name');
            $table->string('maid_last_name');
            
            // Payment details
            $table->decimal('amount', 10, 2);
            $table->string('payment_reference')->unique();
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('gateway')->default('paystack');
            
            // Verification results
            $table->enum('verification_status', ['pending', 'success', 'failed', 'review'])->default('pending');
            $table->json('verification_data')->nullable();
            $table->string('report_path')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standalone_verifications');
    }
};
