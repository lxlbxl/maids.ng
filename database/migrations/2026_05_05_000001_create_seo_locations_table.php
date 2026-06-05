<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_locations', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['city', 'area']);
            $table->string('name', 255);
            $table->string('slug', 100);
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('seo_locations')
                  ->nullOnDelete();

            $table->string('state', 100)->nullable();
            $table->unsignedTinyInteger('tier')->default(1);

            $table->text('description')->nullable();
            $table->text('demand_context')->nullable();
            $table->json('notable_estates')->nullable();
            $table->json('nearby_areas')->nullable();

            $table->unsignedInteger('household_estimate')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['slug', 'parent_id']);
            $table->index(['type', 'is_active']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_locations');
    }
};
