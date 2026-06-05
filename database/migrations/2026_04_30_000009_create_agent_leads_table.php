<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_identity_id')->constrained('agent_channel_identities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->json('intent')->nullable();
            $table->enum('status', ['new', 'warm', 'registered', 'lost'])->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_leads');
    }
};