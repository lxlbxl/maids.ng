<?php

namespace Database\Seeders;

use App\Models\AgentOverride;
use Illuminate\Database\Seeder;

class AgentOverrideSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            'scout', 'sentinel', 'referee', 'concierge',
            'treasurer', 'gatekeeper', 'ambassador',
            'marketer', 'seo_content', 'outreach',
        ];

        foreach ($agents as $agent) {
            AgentOverride::firstOrCreate(
                ['agent_name' => $agent],
                [
                    'mode'                 => 'active',
                    'auto_route_to_human'  => true,
                    'kill_switch'          => false,
                    'daily_spend_cap_usd'  => 10.00,
                    'max_calls_per_hour'   => 200,
                ]
            );
        }

        $this->command->info('Agent override defaults seeded for ' . count($agents) . ' agents.');
    }
}
