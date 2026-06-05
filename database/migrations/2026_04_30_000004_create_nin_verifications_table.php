<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('nin_verifications')) {
            Schema::table('nin_verifications', function (Blueprint $table) {
                if (!Schema::hasColumn('nin_verifications', 'status')) {
                    $table->enum('status', ['pending', 'approved', 'rejected', 'manual_review'])
                        ->default('pending')->after('user_id');
                }
                if (!Schema::hasColumn('nin_verifications', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('nin_verifications', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
                }
                if (!Schema::hasColumn('nin_verifications', 'confidence_score')) {
                    $table->unsignedTinyInteger('confidence_score')->nullable()->after('reviewed_at');
                }
            });
            return;
        }

        Schema::create('nin_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nin_hash', 64)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'manual_review'])
                ->default('pending');
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        // Do not drop if it already existed
    }
};