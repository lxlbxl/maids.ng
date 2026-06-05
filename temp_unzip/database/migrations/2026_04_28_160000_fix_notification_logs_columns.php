<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile notification_logs table to match the NotificationLog model's $fillable.
 * Adds all columns the model expects but the original migration omitted.
 */
return new class extends Migration {
    public function up(): void
    {
        // Drop conflicting indexes before renaming
        try {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->dropIndex('notification_logs_type_status_index');
            });
        } catch (\Exception $e) {
            // Index may not exist
        }

        Schema::table('notification_logs', function (Blueprint $table) {
            // Rename 'type' to 'notification_type' if 'type' exists
            if (Schema::hasColumn('notification_logs', 'type') && !Schema::hasColumn('notification_logs', 'notification_type')) {
                $table->renameColumn('type', 'notification_type');
            }
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            // Add notification_type if neither 'type' nor 'notification_type' existed
            if (!Schema::hasColumn('notification_logs', 'notification_type')) {
                $table->string('notification_type', 50)->default('general')->after('channel');
            }

            // Core fields the model expects
            if (!Schema::hasColumn('notification_logs', 'subject')) {
                $table->string('subject', 255)->nullable()->after('notification_type');
            }
            if (!Schema::hasColumn('notification_logs', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('context_json');
            }
            if (!Schema::hasColumn('notification_logs', 'reference_type')) {
                $table->string('reference_type', 50)->nullable()->after('reference_id');
            }
            if (!Schema::hasColumn('notification_logs', 'status')) {
                $table->string('status', 20)->default('pending')->after('delivery_status');
            }
            if (!Schema::hasColumn('notification_logs', 'delivery_response')) {
                $table->json('delivery_response')->nullable()->after('status');
            }
            if (!Schema::hasColumn('notification_logs', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
            if (!Schema::hasColumn('notification_logs', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('notification_logs', 'requires_follow_up')) {
                $table->boolean('requires_follow_up')->default(false)->after('parent_notification_id');
            }
            if (!Schema::hasColumn('notification_logs', 'follow_up_scheduled_at')) {
                $table->timestamp('follow_up_scheduled_at')->nullable()->after('requires_follow_up');
            }
            if (!Schema::hasColumn('notification_logs', 'ai_prompt_used')) {
                $table->text('ai_prompt_used')->nullable()->after('ai_reasoning');
            }
            if (!Schema::hasColumn('notification_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('ai_prompt_used');
            }
            if (!Schema::hasColumn('notification_logs', 'user_agent')) {
                $table->string('user_agent', 255)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('notification_logs', 'error_message')) {
                $table->text('error_message')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('notification_logs', 'retry_count')) {
                $table->unsignedTinyInteger('retry_count')->default(0)->after('error_message');
            }

            // Ensure escrow_funded exists on salary_schedules (model expects it)
            // This is a defensive check in case the original migration was incomplete
        });

        // Fix indexes — add missing ones
        try {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->index('status', 'notification_logs_status_index');
                $table->index('notification_type', 'notification_logs_notification_type_index');
                $table->index(['reference_id', 'reference_type'], 'notification_logs_reference_index');
            });
        } catch (\Exception $e) {
            // Indexes may already exist — silently continue
        }
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $columns = [
                'subject',
                'reference_id',
                'reference_type',
                'status',
                'delivery_response',
                'delivered_at',
                'read_at',
                'requires_follow_up',
                'follow_up_scheduled_at',
                'ai_prompt_used',
                'ip_address',
                'user_agent',
                'error_message',
                'retry_count',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('notification_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
