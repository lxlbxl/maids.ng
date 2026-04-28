<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maid_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maid_id')->constrained('users')->onDelete('cascade');
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('total_withdrawn', 12, 2)->default(0);
            $table->decimal('pending_withdrawal', 12, 2)->default(0);
            $table->unsignedTinyInteger('salary_day')->nullable()->comment('Day of month salary is expected (1-31)');
            $table->date('employment_start_date')->nullable();
            $table->timestamp('last_salary_paid_at')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->string('timezone', 50)->default('Africa/Lagos');
            $table->timestamps();

            $table->unique('maid_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maid_wallets');
    }
};
