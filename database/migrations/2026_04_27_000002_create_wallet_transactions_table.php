<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_type', 20); // 'employer' or 'maid'
            $table->foreignId('employer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('maid_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transaction_type');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('description');
            $table->string('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 20)->default('completed'); // pending, processing, completed, failed, cancelled
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['wallet_type', 'employer_id']);
            $table->index(['wallet_type', 'maid_id']);
            $table->index(['reference_id', 'reference_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
