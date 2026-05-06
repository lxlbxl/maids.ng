<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_services', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('plural', 100);
            $table->json('also_known_as');

            $table->text('short_description');
            $table->text('full_description');
            $table->text('duties');
            $table->text('who_needs_this');
            $table->text('what_to_look_for');

            $table->unsignedInteger('salary_min');
            $table->unsignedInteger('salary_max');
            $table->json('salary_by_city');

            $table->boolean('live_in_available')->default(true);
            $table->boolean('part_time_available')->default(true);
            $table->boolean('nin_required')->default(true);

            $table->string('schema_service_type', 255)->nullable();
            $table->string('meta_title_template', 70)->nullable();
            $table->string('meta_description_template', 160)->nullable();
            $table->unsignedTinyInteger('demand_index')->default(50);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_services');
    }
};
