<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table) {
            $table->id();

            $table->string('page_type', 50);
            $table->string('url_path', 500)->unique();

            $table->foreignId('location_id')
                  ->nullable()
                  ->constrained('seo_locations')
                  ->nullOnDelete();

            $table->foreignId('service_id')
                  ->nullable()
                  ->constrained('seo_services')
                  ->nullOnDelete();

            $table->json('content_blocks')->nullable();

            $table->string('meta_title', 70);
            $table->string('meta_description', 160);
            $table->string('h1', 100);
            $table->string('canonical_url', 500)->nullable();

            $table->json('schema_markup')->nullable();

            $table->enum('page_status', ['draft', 'published', 'noindex', 'redirected'])
                  ->default('draft');

            $table->unsignedTinyInteger('content_score')->default(0);

            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->float('avg_position')->nullable();
            $table->float('ctr')->nullable();

            $table->timestamp('content_generated_at')->nullable();
            $table->timestamp('last_indexed_at')->nullable();
            $table->timestamps();

            $table->index(['page_type', 'page_status']);
            $table->index(['location_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
    }
};
