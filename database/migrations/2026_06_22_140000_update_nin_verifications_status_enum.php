<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing constraint regardless of its definition
        DB::statement('ALTER TABLE nin_verifications DROP CONSTRAINT IF EXISTS nin_verifications_status_check');

        // Change column type to string so we can widen the enum
        Schema::table('nin_verifications', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        // Re-create with expanded values — skip if already exists with correct definition
        $existing = DB::select(
            "SELECT pg_get_constraintdef(oid) as def FROM pg_constraint WHERE conname = 'nin_verifications_status_check' AND conrelid = 'nin_verifications'::regclass"
        );

        if (empty($existing)) {
            DB::statement("ALTER TABLE nin_verifications ADD CONSTRAINT nin_verifications_status_check CHECK (
                status::text = ANY (ARRAY[
                    'pending', 'approved', 'rejected', 'manual_review',
                    'verified', 'review_required', 'failed'
                ])
            )");
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE nin_verifications DROP CONSTRAINT IF EXISTS nin_verifications_status_check');

        Schema::table('nin_verifications', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        $existing = DB::select(
            "SELECT pg_get_constraintdef(oid) as def FROM pg_constraint WHERE conname = 'nin_verifications_status_check' AND conrelid = 'nin_verifications'::regclass"
        );

        if (empty($existing)) {
            DB::statement("ALTER TABLE nin_verifications ADD CONSTRAINT nin_verifications_status_check CHECK (
                status::text = ANY (ARRAY[
                    'pending', 'approved', 'rejected', 'manual_review'
                ])
            )");
        }
    }
};
