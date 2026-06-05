<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_faqs', function (Blueprint $table) {
            $table->id();

            $table->string('question', 500);
            $table->text('answer');
            $table->text('short_answer');
            $table->string('slug', 500)->unique();

            $table->foreignId('service_id')->nullable()->constrained('seo_services')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('seo_locations')->nullOnDelete();

            $table->enum('category', [
                'pricing', 'process', 'verification', 'legal',
                'service_type', 'platform', 'salary', 'general'
            ]);

            $table->json('embedded_on_page_types')->nullable();
            $table->boolean('targets_paa')->default(false);
            $table->unsignedInteger('estimated_monthly_searches')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_faqs');
    }
};
