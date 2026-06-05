<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $keys = [
            // ── Public Frontend Scripts ──
            // Google (GTM / GA4)
            'script_google_head_frontend',
            'script_google_body_frontend',
            'script_google_footer_frontend',
            // Meta (Facebook Pixel)
            'script_meta_head_frontend',
            'script_meta_body_frontend',
            'script_meta_footer_frontend',
            // Custom Third-Party
            'script_custom_head_frontend',
            'script_custom_body_frontend',
            'script_custom_footer_frontend',

            // ── Member Area Scripts (Employer / Maid dashboards) ──
            // Google (GTM / GA4)
            'script_google_head_member',
            'script_google_body_member',
            'script_google_footer_member',
            // Meta (Facebook Pixel)
            'script_meta_head_member',
            'script_meta_body_member',
            'script_meta_footer_member',
            // Custom Third-Party
            'script_custom_head_member',
            'script_custom_body_member',
            'script_custom_footer_member',
        ];

        foreach ($keys as $key) {
            DB::table('settings')->insertOrIgnore([
                'key'          => $key,
                'value'        => '',
                'is_encrypted' => false,
                'group'        => 'scripts',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = [
            'script_google_head_frontend',
            'script_google_body_frontend',
            'script_google_footer_frontend',
            'script_meta_head_frontend',
            'script_meta_body_frontend',
            'script_meta_footer_frontend',
            'script_custom_head_frontend',
            'script_custom_body_frontend',
            'script_custom_footer_frontend',
            'script_google_head_member',
            'script_google_body_member',
            'script_google_footer_member',
            'script_meta_head_member',
            'script_meta_body_member',
            'script_meta_footer_member',
            'script_custom_head_member',
            'script_custom_body_member',
            'script_custom_footer_member',
        ];

        DB::table('settings')->whereIn('key', $keys)->delete();
    }
};
