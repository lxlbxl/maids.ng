<?php

namespace App\Services;

use App\Models\AgentKnowledgeBase;
use App\Models\AgentPromptTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * KnowledgeService — Central prompt and knowledge base management.
 *
 * Every agent calls this service at the start of each invocation to get
 * its assembled system prompt, which includes:
 * 1. The agent's prompt template (with live pricing placeholders replaced)
 * 2. Relevant knowledge base articles (filtered by agent + tier)
 * 3. Live pricing & fees from the Settings table
 * 4. Current context (date, tier, agent name)
 *
 * Results are cached for 5 minutes by default.
 */
class KnowledgeService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Master method. Called by every agent at the start of every invocation.
     *
     * @param string $agentName  The agent's self-identifier string.
     * @param string $tier       The user tier: guest, lead, authenticated, admin.
     * @return string            Fully assembled system prompt ready for LLM.
     * @throws \RuntimeException If no active prompt template found for agent+tier.
     */
    public function buildContext(string $agentName, string $tier = 'guest'): string
    {
        $cacheKey = "agent_context_{$agentName}_{$tier}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($agentName, $tier) {
            return $this->assemble($agentName, $tier);
        });
    }

    /**
     * Force-clear the cache for a specific agent+tier or all contexts.
     */
    public function flushCache(?string $agentName = null, ?string $tier = null): void
    {
        if ($agentName && $tier) {
            Cache::forget("agent_context_{$agentName}_{$tier}");
            return;
        }

        $agents = [
            'ambassador',
            'scout',
            'sentinel',
            'referee',
            'concierge',
            'treasurer',
            'gatekeeper'
        ];
        $tiers = ['guest', 'lead', 'authenticated', 'admin'];

        foreach ($agents as $a) {
            foreach ($tiers as $t) {
                Cache::forget("agent_context_{$a}_{$t}");
            }
        }
    }

    private function assemble(string $agentName, string $tier): string
    {
        $prompt = $this->fetchPromptTemplate($agentName, $tier);
        $kb = $this->fetchKnowledgeBase($agentName, $tier);
        $pricing = $this->fetchPricingContext();
        $prompt = $this->replacePlaceholders($prompt, $pricing);

        $sections = [];

        $sections[] = $prompt;

        if (!empty($kb)) {
            $sections[] = "\n\n---\n## KNOWLEDGE BASE\n\n" . $kb;
        }

        $sections[] = "\n\n---\n## LIVE PRICING & FEES\n\n"
            . "The following values are live from the platform settings. "
            . "Always use these exact figures when discussing cost. "
            . "Never guess or approximate pricing.\n\n"
            . $pricing['formatted'];

        $sections[] = "\n\n---\n## CURRENT CONTEXT\n\n"
            . "- Today's date: " . now()->format('l, d F Y') . "\n"
            . "- User tier: {$tier}\n"
            . "- Agent: {$agentName}";

        return implode('', $sections);
    }

    private function fetchPromptTemplate(string $agentName, string $tier): string
    {
        $template = AgentPromptTemplate::active()
            ->forAgent($agentName)
            ->forTier($tier)
            ->first();

        if (!$template) {
            if ($tier !== 'guest') {
                Log::warning("No active prompt template for {$agentName}/{$tier}. Falling back to guest tier.");
                return $this->fetchPromptTemplate($agentName, 'guest');
            }

            // Return a safe default prompt instead of throwing — agents should degrade gracefully
            Log::warning("No active prompt template for {$agentName}/{$tier}. Using default fallback prompt.");
            return "You are an AI assistant for Maids.ng, a Nigerian domestic help marketplace. Be helpful, professional, and concise.";
        }

        return $template->system_prompt;
    }

    private function fetchKnowledgeBase(string $agentName, string $tier): string
    {
        $articles = AgentKnowledgeBase::active()
            ->forAgent($agentName)
            ->forTier($tier)
            ->ordered()
            ->get();

        if ($articles->isEmpty()) {
            return '';
        }

        return $articles->map(function ($article) {
            return "### [{$article->category}] {$article->title}\n\n{$article->content}";
        })->join("\n\n---\n\n");
    }

    private function fetchPricingContext(): array
    {
        $raw = [
            'matching_fee' => (int) \App\Models\Setting::get('matching_fee', 5000),
            'premium_matching_fee' => (int) \App\Models\Setting::get('premium_matching_fee', 15000),
            'commission_rate' => (float) \App\Models\Setting::get('commission_rate', 15),
            'guarantee_period_days' => (int) \App\Models\Setting::get('guarantee_period_days', 10),
            'maid_monthly_rate_min' => (int) \App\Models\Setting::get('maid_monthly_rate_min', 30000),
            'maid_monthly_rate_max' => (int) \App\Models\Setting::get('maid_monthly_rate_max', 80000),
            'withdrawal_minimum' => (int) \App\Models\Setting::get('withdrawal_minimum', 5000),
            'escrow_release_days' => (int) \App\Models\Setting::get('escrow_release_days', 3),
        ];

        $formatted = collect([
            "- Standard Matching Fee: ₦" . number_format($raw['matching_fee']),
            "- Premium Matching Fee: ₦" . number_format($raw['premium_matching_fee']),
            "- Platform Commission: " . $raw['commission_rate'] . "% of salary",
            "- Money-Back Guarantee: " . $raw['guarantee_period_days'] . " days",
            "- Maid Monthly Rate Range: ₦" . number_format($raw['maid_monthly_rate_min'])
            . " – ₦" . number_format($raw['maid_monthly_rate_max']),
            "- Minimum Withdrawal: ₦" . number_format($raw['withdrawal_minimum']),
            "- Escrow Release: " . $raw['escrow_release_days'] . " days after service confirmation",
        ])->join("\n");

        return array_merge($raw, ['formatted' => $formatted]);
    }

    private function replacePlaceholders(string $prompt, array $pricing): string
    {
        $replacements = [
            '{{AGENT_NAME}}' => 'Maids.ng AI Assistant',
            '{{BUSINESS_NAME}}' => 'Maids.ng',
            '{{MATCHING_FEE}}' => '₦' . number_format($pricing['matching_fee']),
            '{{COMMISSION_RATE}}' => $pricing['commission_rate'] . '%',
            '{{GUARANTEE_DAYS}}' => $pricing['guarantee_period_days'] . ' days',
            '{{CURRENT_DATE}}' => now()->format('d F Y'),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $prompt
        );
    }
}