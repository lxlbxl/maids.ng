<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use App\Models\Setting;

class OpenRouterDriver implements AiProvider
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = Setting::get('openrouter_key');
        $this->model = Setting::get('openrouter_model', 'google/gemini-flash-1.5');
    }

    public function chat(string $prompt, array $options = []): array
    {
        if (!$this->apiKey) {
            return ['error' => 'OpenRouter API key missing.', 'message' => 'Please configure your OpenRouter key in System Settings.'];
        }

        $response = Http::withToken($this->apiKey)
            ->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'Maids.ng Mission Control',
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $options['model'] ?? $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $options['system_prompt'] ?? 'You are an AI agent operating a marketplace for household help.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => $options['temperature'] ?? 0.7,
            ]);

        if ($response->failed()) {
            return ['error' => 'OpenRouter API Call Failed', 'message' => $response->json('error.message', 'Unknown error')];
        }

        return [
            'content' => $response->json('choices.0.message.content'),
            'usage' => $response->json('usage'),
            'raw' => $response->json()
        ];
    }

    public function getModels(): array
    {
        return [
            'google/gemini-flash-1.5' => 'Gemini 1.5 Flash (Fastest)',
            'google/gemini-pro-1.5' => 'Gemini 1.5 Pro',
            'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (Coding/Logic)',
            'anthropic/claude-3-opus' => 'Claude 3 Opus',
            'meta-llama/llama-3.1-405b' => 'Llama 3.1 405B',
            'mistralai/mistral-large-2407' => 'Mistral Large 2',
        ];
    }
}
