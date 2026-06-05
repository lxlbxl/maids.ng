<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standalone_verifications', function (Blueprint $table) {
            $table->integer('verification_attempts')->default(0)->after('verification_status');
            $table->string('last_api_status_code', 10)->nullable()->after('verification_attempts');
            $table->text('last_api_error')->nullable()->after('last_api_status_code');
            $table->boolean('qoreid_product_available')->nullable()->after('last_api_error');
            $table->string('verification_status_detail')->nullable()->after('qoreid_product_available');
        });
    }

    public function down(): void
    {
        Schema::table('standalone_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'verification_attempts',
                'last_api_status_code',
                'last_api_error',
                'qoreid_product_available',
                'verification_status_detail',
            ]);
        });
    }
};
