<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('standalone_verifications', function (Blueprint $table) {
            // Optional maid details for better QoreID match accuracy
            $table->string('maid_middle_name')->nullable()->after('maid_last_name');
            $table->date('maid_dob')->nullable()->after('maid_middle_name');
            $table->string('maid_phone')->nullable()->after('maid_dob');
            $table->string('maid_email')->nullable()->after('maid_phone');
            $table->string('maid_gender')->nullable()->after('maid_email');

            // QoreID verification metadata
            $table->integer('confidence_score')->default(0)->after('verification_data');
            $table->boolean('name_matched')->default(false)->after('confidence_score');
            $table->string('external_reference')->nullable()->after('name_matched');

            // Store optional fields sent to QoreID
            $table->json('optional_fields')->nullable()->after('external_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('standalone_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'maid_middle_name',
                'maid_dob',
                'maid_phone',
                'maid_email',
                'maid_gender',
                'confidence_score',
                'name_matched',
                'external_reference',
                'optional_fields',
            ]);
        });
    }
};