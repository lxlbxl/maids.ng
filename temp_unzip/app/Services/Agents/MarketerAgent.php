<?php

namespace App\Services\Agents;

use App\Agents\Concerns\LogsEvents;
use App\Models\{SocialPost, SocialTheme};
use App\Services\Ai\AiService;
use App\Services\KnowledgeService;

class MarketerAgent
{
    use LogsEvents;

    protected string $agentName = 'marketer';

    public function __construct(
        private readonly AiService $ai,
        private readonly KnowledgeService $knowledge,
    ) {}

    public function generatePost(?string $funnelStage = null, ?int $themeId = null): array
    {
        if (!$this->canProceed(
            'post.generate',
            'generate_content',
            'Generate social media content post',
            ['funnel_stage' => $funnelStage, 'theme_id' => $themeId]
        )) {
            return [];
        }

        $startTime = now();

        $stage = $funnelStage ?? 'awareness';
        $stageContext = $this->getStageContext($stage);

        $theme = $themeId ? SocialTheme::find($themeId) : SocialTheme::active()->inRandomOrder()->first();
        $themeContext = $theme ? "\nTheme: {$theme->name} — {$theme->description}\nTone: {$theme->tone}" : '';

        $systemPrompt = $this->knowledge->buildContext('marketer', 'admin');
        $userPrompt = "Generate a social media post for the {$stage} funnel stage.{$themeContext}\n\n" .
            "{$stageContext}\n\n" .
            "Return valid JSON: {\"hook\": \"...\", \"caption\": \"...\", \"hashtags\": [\"...\"], " .
            "\"call_to_action\": \"...\", \"image_description\": \"...\", \"format\": \"image|carousel|text\"}";

        try {
            $response = $this->ai->chat($userPrompt, ['system_prompt' => $systemPrompt]);
            $content = $this->parseJsonResponse($response['content'] ?? '');

            $post = SocialPost::create([
                'theme_id'           => $theme?->id,
                'format'             => $content['format'] ?? 'image',
                'funnel_stage'       => $stage,
                'hook'               => $content['hook'] ?? '',
                'caption'            => $content['caption'] ?? '',
                'hashtags'           => $content['hashtags'] ?? [],
                'call_to_action'     => $content['call_to_action'] ?? '',
                'image_description'  => $content['image_description'] ?? '',
                'platforms'           => ['instagram', 'facebook', 'twitter'],
                'status'             => 'draft',
                'ai_model'           => $response['model'] ?? null,
                'prompt_tokens'      => $response['usage']['prompt_tokens'] ?? null,
                'completion_tokens'  => $response['usage']['completion_tokens'] ?? null,
                'total_tokens'       => $response['usage']['total_tokens'] ?? null,
            ]);

            $this->logEvent(
                'post.generated',
                'info',
                "Generated {$post->format} post — " . ($theme?->name ?? 'general') . " ({$stage})",
                [
                    'post_id'   => $post->id,
                    'theme'     => $theme?->name,
                    'funnel'    => $stage,
                    'format'    => $post->format,
                    'hook'      => $post->hook,
                    'platforms' => $post->platforms,
                ],
                [
                    'related_model' => 'SocialPost',
                    'related_id'    => $post->id,
                    'tokens'        => $response['usage'] ?? null,
                    'model'         => $response['model'] ?? null,
                    'duration_ms'   => now()->diffInMilliseconds($startTime),
                ]
            );

            return ['post' => $post, 'content' => $content];
        } catch (\Exception $e) {
            $this->getLogger()->logError(
                $this->agentName,
                'post.generation_failed',
                "Failed to generate post: {$e->getMessage()}",
                $e
            );
            throw $e;
        }
    }

    private function getStageContext(string $stage): string
    {
        return match ($stage) {
            'awareness'      => "Goal: Introduce Maids.ng to potential employers. Highlight the problem of finding reliable domestic help in Nigeria. Focus on trust, safety, and NIN verification.",
            'consideration'  => "Goal: Show why Maids.ng is the best solution. Highlight matching algorithm, verified maids, guarantee match feature. Include social proof.",
            'conversion'     => "Goal: Drive sign-ups and onboarding. Strong call-to-action to start the matching quiz. Limited-time urgency if applicable.",
            'retention'      => "Goal: Keep existing users engaged. Share tips, success stories, maintenance reminders. Cross-sell additional services.",
            'advocacy'       => "Goal: Encourage referrals and reviews. Share user testimonials. Incentivize sharing.",
            default          => "Goal: Create engaging content about domestic help solutions in Nigeria. Focus on value proposition and trust.",
        };
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
