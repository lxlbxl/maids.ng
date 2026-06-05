<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->enum('trigger_type', ['manual', 'scheduled', 'event', 'webhook'])->default('manual');
            $table->string('preferred_channel', 50)->default('email');
            $table->boolean('is_active')->default(true);
            $table->string('schedule_cron', 50)->nullable();
            $table->unsignedInteger('max_contacts_per_day')->default(100);
            $table->text('message_template')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('agent_outreach_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('agent_campaigns')->cascadeOnDelete();
            $table->foreignId('channel_identity_id')->nullable()->constrained('agent_channel_identities')->nullOnDelete();
            $table->string('channel', 50);
            $table->text('message_content');
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->json('response_data')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['channel', 'sent_at']);
        });

        Schema::create('social_themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('tone', 50)->default('professional');
            $table->string('target_audience', 100)->default('employers');
            $table->timestamps();
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->nullable()->constrained('social_themes')->nullOnDelete();
            $table->enum('format', ['image', 'carousel', 'video', 'text', 'story'])->default('image');
            $table->string('funnel_stage', 50)->default('awareness');
            $table->text('hook')->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->text('call_to_action')->nullable();
            $table->text('image_description')->nullable();
            $table->json('platforms')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('ai_model', 100)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 8, 6)->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('social_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->enum('media_type', ['image', 'video', 'gif'])->default('image');
            $table->string('file_path', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_media');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_themes');
        Schema::dropIfExists('agent_outreach_logs');
        Schema::dropIfExists('agent_campaigns');
    }
};
