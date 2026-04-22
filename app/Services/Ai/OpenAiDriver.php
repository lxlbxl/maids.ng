<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use App\Models\Setting;

class OpenAiDriver implements AiProvider
{
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->apiKey = Setting::get('openai_key');
        $this->model = Setting::get('openai_model', 'gpt-4o-mini');
    }

    public function chat(string $prompt, array $options = []): array
    {
        if (!$this->apiKey) {
            return ['error' => 'OpenAI API key missing.', 'message' => 'Please configure your OpenAI key in System Settings.'];
        }

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $options['model'] ?? $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $options['system_prompt'] ?? 'You are an AI agent operating a marketplace for household help.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => $options['temperature'] ?? 0.7,
            ]);

        if ($response->failed()) {
            return ['error' => 'OpenAI API Call Failed', 'message' => $response->json('error.message', 'Unknown error')];
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
            'gpt-4o' => 'GPT-4o (Most Intelligent)',
            'gpt-4o-mini' => 'GPT-4o Mini (Fast & Cheap)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'o1-mini-preview' => 'o1 Mini (Reasoning)',
            'o1-preview' => 'o1 Preview',
        ];
    }
}
