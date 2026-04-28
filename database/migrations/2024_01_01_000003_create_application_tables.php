<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maid_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->json('skills')->nullable();
            $table->integer('experience_years')->default(0);
            $table->json('help_types')->nullable();
            $table->string('schedule_preference')->nullable();
            $table->integer('expected_salary')->nullable();
            $table->string('location')->nullable();
            $table->string('state')->nullable();
            $table->string('lga')->nullable();
            $table->boolean('nin_verified')->default(false);
            $table->boolean('background_verified')->default(false);
            $table->enum('availability_status', ['available', 'busy', 'unavailable'])->default('available');
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->timestamps();
        });

        Schema::create('employer_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->cascadeOnDelete();
            $table->json('help_types')->nullable();
            $table->string('schedule')->nullable();
            $table->string('urgency')->nullable();
            $table->string('location')->nullable();
            $table->string('state')->nullable();
            $table->integer('budget_min')->nullable();
            $table->integer('budget_max')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->foreignId('selected_maid_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('matching_status', ['pending', 'matched', 'paid', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('matching_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preference_id')->constrained('employer_preferences')->cascadeOnDelete();
            $table->foreignId('employer_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reference')->unique();
            $table->string('gateway')->default('paystack');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('maid_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('preference_id')->nullable()->constrained('employer_preferences')->nullOnDelete();
            $table->enum('status', ['pending', 'accepted', 'active', 'completed', 'cancelled'])->default('pending');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('schedule_type')->nullable();
            $table->integer('agreed_salary')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('maid_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rating')->unsigned();
            $table->text('comment')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('matching_fee_payments');
        Schema::dropIfExists('employer_preferences');
        Schema::dropIfExists('maid_profiles');
    }
};
