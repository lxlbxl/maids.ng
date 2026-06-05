<?php

namespace App\Services\Agents;

use App\Agents\Concerns\LogsEvents;
use App\Models\SeoPage;
use App\Services\Ai\AiService;
use App\Services\KnowledgeService;

class SeoContentAgent
{
    use LogsEvents;

    protected string $agentName = 'seo_content';

    public function __construct(
        private readonly AiService $ai,
        private readonly KnowledgeService $knowledge,
    ) {}

    public function generatePageContent(SeoPage $page): array
    {
        if (!$this->canProceed(
            'content.generate',
            'generate_seo_content',
            "Generate SEO content for {$page->url_path}",
            ['page_id' => $page->id]
        )) {
            return [];
        }

        $startTime = now();

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt    = $this->buildUserPrompt($page);

        try {
            $response = $this->ai->chat($userPrompt, ['system_prompt' => $systemPrompt]);
            $content   = $this->parseJsonResponse($response['content'] ?? '');

            $score = $this->scoreContent($content);

            $page->update([
                'content_blocks'       => $content,
                'content_score'        => $score,
                'page_status'          => $score >= 65 ? 'published' : 'draft',
                'content_generated_at' => now(),
            ]);

            $this->logEvent(
                'content.generated',
                $page->page_status === 'published' ? 'success' : 'warning',
                "Generated content for {$page->url_path} — score: {$score}/100",
                [
                    'page_id'    => $page->id,
                    'url'        => $page->url_path,
                    'page_type'  => $page->page_type,
                    'score'      => $score,
                    'status'     => $page->page_status,
                    'faq_count'  => count($content['faqs'] ?? []),
                ],
                [
                    'related_model' => 'SeoPage',
                    'related_id'    => $page->id,
                    'tokens'        => $response['usage'] ?? null,
                    'model'         => $response['model'] ?? null,
                    'duration_ms'   => now()->diffInMilliseconds($startTime),
                ]
            );

            return ['page' => $page->fresh(), 'content' => $content, 'score' => $score];
        } catch (\Exception $e) {
            $this->getLogger()->logError(
                $this->agentName,
                'content.generation_failed',
                "Failed to generate content for {$page->url_path}: {$e->getMessage()}",
                $e,
                ['related_model' => 'SeoPage', 'related_id' => $page->id]
            );
            throw $e;
        }
    }

    private function buildSystemPrompt(): string
    {
        return $this->knowledge->buildContext('seo_content', 'admin') ?: (
            "You are an expert SEO content writer for Maids.ng, a Nigerian domestic help marketplace. " .
            "Write helpful, accurate, engaging content optimized for search engines. " .
            "Always include local Nigerian context, mention NIN verification as a trust signal, " .
            "and use a professional but friendly tone. " .
            "Return your response as valid JSON with the structure requested."
        );
    }

    private function buildUserPrompt(SeoPage $page): string
    {
        $location = $page->location;
        $service  = $page->service;

        $lines = ["Generate full page content for: {$page->url_path}", ''];
        $lines[] = "Page Type: {$page->page_type}";
        if ($location) {
            $lines[] = "Location: {$location->name}" . ($location->state ? ", {$location->state}" : '');
            if ($location->description) $lines[] = "Area context: {$location->description}";
            if ($location->demand_context) $lines[] = "Local demand: {$location->demand_context}";
        }
        if ($service) {
            $lines[] = "Service: {$service->name}";
            if ($service->short_description) $lines[] = "Description: {$service->short_description}";
            if ($service->salary_min && $service->salary_max) {
                $lines[] = "Salary range: ₦" . number_format($service->salary_min) . " – ₦" . number_format($service->salary_max);
            }
        }
        $lines[] = '';
        $lines[] = 'Return JSON:';
        $lines[] = '{';
        $lines[] = '  "intro": "2-3 opening sentences",';
        $lines[] = '  "what_is_this": "explain what this page covers",';
        $lines[] = '  "why_maids_ng": "why choose Maids.ng platform",';
        $lines[] = '  "local_context": "location-specific details",';
        $lines[] = '  "hiring_tips": [{"tip":"...","explanation":"..."}],';
        $lines[] = '  "salary_section": "salary information",';
        $lines[] = '  "faqs": [{"question":"...","short_answer":"...","full_answer":"..."}],';
        $lines[] = '  "cta_text": "call to action under 20 words",';
        $lines[] = '  "what_to_check": "verification checklist"';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function scoreContent(array $content): int
    {
        $score = 0;
        if (!empty($content['intro'])) $score += 15;
        if (!empty($content['why_maids_ng'])) $score += 15;
        if (!empty($content['faqs'])) $score += 15;
        if (!empty($content['cta_text'])) $score += 15;
        if (count($content['faqs'] ?? []) >= 5) $score += 10;
        if (!empty($content['local_context'])) $score += 10;
        if (stripos(json_encode($content), '₦') !== false) $score += 5;
        if (!empty($content['hiring_tips'])) $score += 5;
        return min($score, 100);
    }

    private function parseJsonResponse(string $content): array
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*\n?/', '', $content);
            $content = preg_replace('/\n?```\s*$/', '', $content);
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }
}
