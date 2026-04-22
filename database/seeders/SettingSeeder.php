<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // AI Configuration
        \App\Models\Setting::set('ai_active_provider', 'openai', 'ai');
        \App\Models\Setting::set('openai_model', 'gpt-4o-mini', 'ai');
        \App\Models\Setting::set('openrouter_model', 'google/gemini-flash-1.5', 'ai');
        
        // Placeholder Keys (Masked in UI)
        \App\Models\Setting::set('openai_key', '', 'ai', true);
        \App\Models\Setting::set('openrouter_key', '', 'ai', true);

        // General Settings
        \App\Models\Setting::set('platform_name', 'Maids.ng', 'general');
        \App\Models\Setting::set('service_fee_percentage', '10', 'finance');
    }
}
