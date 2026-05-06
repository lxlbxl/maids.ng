<?php

namespace App\Services\Agents\Tools;

use App\Models\AgentChannelIdentity;
use App\Services\Agents\DTOs\InboundMessage;

/**
 * Tool: get_pricing
 * Returns current Maids.ng pricing and fee information from settings.
 * Always uses live data — never hardcodes values.
 */
class GetPricingTool
{
    public function __invoke(array $args, AgentChannelIdentity $identity, InboundMessage $message): string
    {
        return json_encode([
            'matching_fee' => [
                'standard' => setting('matching_fee', 5000),
                'premium' => setting('premium_matching_fee', 15000),
                'description' => 'One-time fee to access matched maid profiles and contact details.',
            ],
            'commission_rate' => [
                'rate' => setting('commission_rate', 15),
                'description' => 'Platform commission deducted from maid salary on escrow release.',
            ],
            'guarantee' => [
                'period_days' => setting('guarantee_period_days', 10),
                'description' => 'Money-back guarantee window from maid start date.',
            ],
            'salary_range' => [
                'min' => setting('maid_monthly_rate_min', 30000),
                'max' => setting('maid_monthly_rate_max', 80000),
                'currency' => 'NGN (₦)',
            ],
            'withdrawal' => [
                'minimum' => setting('withdrawal_minimum', 5000),
                'processing_days' => '1-3 business days',
            ],
            'escrow' => [
                'release_days' => setting('escrow_release_days', 3),
                'description' => 'Days after service confirmation before funds are released to maid.',
            ],
        ]);
    }

    public function description(): string
    {
        return 'Get current Maids.ng pricing, fees, and policy information. Always returns live data from platform settings.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
    }
}