<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('escrow_balance', 12, 2)->default(0);
            $table->decimal('total_deposited', 12, 2)->default(0);
            $table->decimal('total_refunded', 12, 2)->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('timezone', 50)->default('Africa/Lagos');
            $table->timestamps();

            $table->unique('employer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_wallets');
    }
};
