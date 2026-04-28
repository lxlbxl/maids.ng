<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id');
            $table->string('wallet_type', 20); // 'employer' or 'maid'
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference')->unique();
            $table->string('description');
            $table->string('source_type')->nullable(); // 'matching_fee', 'refund', 'salary', 'withdrawal'
            $table->foreignId('source_id')->nullable();
            $table->foreignId('related_preference_id')->nullable()->constrained('employer_preferences')->nullOnDelete();
            $table->foreignId('related_assignment_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 20)->default('completed'); // pending, completed, failed
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'wallet_type']);
            $table->index('reference');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
