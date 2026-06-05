<?php

namespace App\Services;

use App\Models\{SeoPage, SeoFaq};
use App\Services\Ai\AiService;
use App\Services\KnowledgeService;

class SeoContentGenerator
{
    private const MIN_QUALITY_SCORE = 65;

    public function __construct(
        private AiService $ai,
        private KnowledgeService $knowledge,
    ) {}

    public function generate(SeoPage $page): void
    {
        $systemPrompt = $this->buildSystemPrompt($page);
        $userPrompt   = $this->buildUserPrompt($page);

        $systemPrompt .= "\n\nRespond ONLY with valid JSON matching this schema:\n" . $this->getSchema($page);

        $result  = $this->ai->chat($systemPrompt . "\n\n" . $userPrompt);

        $jsonStr = $result;
        if (is_array($result) && isset($result['content'])) {
            $jsonStr = $result['content'];
        }

        // Try to extract JSON from possible markdown code blocks
        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $jsonStr, $matches)) {
            $jsonStr = $matches[1];
        }

        $content = json_decode($jsonStr, true);

        if (!$content) {
            \Log::error("SeoContentGenerator: Invalid JSON for page {$page->id}");
            return;
        }

        $score = $this->scoreContent($content, $page);

        $page->update([
            'content_blocks'       => $content,
            'content_score'        => $score,
            'page_status'          => $score >= self::MIN_QUALITY_SCORE ? 'published' : 'noindex',
            'content_generated_at' => now(),
        ]);
    }

    private function buildSystemPrompt(SeoPage $page): string
    {
        $kb = $this->knowledge->buildContext('concierge', 'global');

        return $kb . "\n\n---\n## YOUR ROLE\n"
            . "You are an expert SEO content writer for Maids.ng, Nigeria's leading domestic staff matching platform. "
            . "You write content that ranks on Google AND gets cited by AI models like ChatGPT and Perplexity. "
            . "Your writing is:\n"
            . "- Factually accurate (use only data provided — never fabricate statistics)\n"
            . "- Genuinely helpful to someone considering hiring domestic staff in Nigeria\n"
            . "- Written in clear, warm, professional Nigerian-English\n"
            . "- Structured for both humans and AI crawlers (clear H2s, concise answers)\n"
            . "- Never keyword-stuffed or generic\n"
            . "- Always specific to the exact location and service type\n\n"
            . "CRITICAL RULES:\n"
            . "- Never fabricate testimonials or reviews\n"
            . "- Use only salary figures provided in the context\n"
            . "- Never mention competitor platforms by name\n"
            . "- Always reference the 10-day money-back guarantee when mentioning Maids.ng's offer\n"
            . "- Write as if you know this specific neighbourhood well\n";
    }

    private function buildUserPrompt(SeoPage $page): string
    {
        $service  = $page->service;
        $location = $page->location;

        $context = "Page type: {$page->page_type}\n";
        $context .= "H1: {$page->h1}\n";

        if ($service) {
            $salary = $location
                ? $service->getSalaryForCity($location->parent?->slug ?? $location->slug)
                : ['min' => $service->salary_min, 'max' => $service->salary_max];

            $context .= "Service: {$service->name}\n";
            $context .= "Also known as: " . implode(', ', $service->also_known_as ?? []) . "\n";
            $context .= "What they do: {$service->duties}\n";
            $context .= "Salary range for this location: ₦" . number_format($salary['min']) . " – ₦" . number_format($salary['max']) . " per month\n";
            $context .= "NIN verification required: " . ($service->nin_required ? 'Yes' : 'No') . "\n";
        }

        if ($location) {
            $context .= "Location: {$location->full_name}\n";
            $context .= "Location type: {$location->type}\n";
            if ($location->description) {
                $context .= "About this area: {$location->description}\n";
            }
            if ($location->demand_context) {
                $context .= "Why domestic staff are in demand here: {$location->demand_context}\n";
            }
            if ($location->notable_estates) {
                $context .= "Notable estates/areas: " . implode(', ', $location->notable_estates) . "\n";
            }
        }

        return "Generate SEO content for this page.\n\n{$context}\n\nGenerate the content blocks now. JSON only.";
    }

    private function getSchema(SeoPage $page): string
    {
        return json_encode([
            'intro'              => '2–3 opening sentences. Directly answers the search query. Mention the service, location, and what Maids.ng offers.',
            'what_is_this'       => 'For service pages: 1 paragraph explaining what a [service] does. For location pages: about hiring in this area.',
            'why_maids_ng'       => '1 paragraph on why to use Maids.ng specifically. Reference NIN verification, matching algorithm, 10-day guarantee.',
            'local_context'      => 'Specific paragraph about domestic staff demand in this exact location. Should reference local context, estates, types of families who hire.',
            'hiring_tips'        => 'Array of 3–5 objects: {tip: string, explanation: string}. Specific to the service type.',
            'salary_section'     => 'Paragraph explaining salary expectations. Use exact figures provided. Explain factors that affect pay.',
            'faqs'               => 'Array of 5–7 FAQ objects: {question: string, short_answer: string (under 60 words), full_answer: string (150-300 words)}. Target real user questions. Include at least one pricing question and one process question.',
            'cta_text'           => 'A single compelling sentence inviting the user to start the quiz. Under 20 words.',
            'what_to_check'      => 'What to verify before hiring (documents, references, trial period). 2–3 sentences.',
        ], JSON_PRETTY_PRINT);
    }

    private function scoreContent(array $content, SeoPage $page): int
    {
        $score = 0;

        $requiredFields = ['intro', 'why_maids_ng', 'faqs', 'cta_text'];
        foreach ($requiredFields as $field) {
            if (!empty($content[$field])) $score += 15;
        }

        if (count($content['faqs'] ?? []) >= 5) $score += 10;

        if ($page->location && str_contains(
            strtolower(json_encode($content)),
            strtolower($page->location->name)
        )) {
            $score += 10;
        }

        if ($page->service && str_contains(json_encode($content), '₦')) {
            $score += 5;
        }

        return min(100, $score);
    }
}
