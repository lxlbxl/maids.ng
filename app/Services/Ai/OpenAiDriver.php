<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    /**
     * Fetch available models from OpenAI API
     */
    public function fetchModelsFromApi(): array
    {
        if (!$this->apiKey) {
            return ['error' => 'OpenAI API key is not configured. Please enter your OpenAI API key below, save settings, then refresh models.'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->get('https://api.openai.com/v1/models');

            if ($response->failed()) {
                $errorMsg = $response->json('error.message', 'Unknown error');
                Log::warning('OpenAI model fetch failed', ['status' => $response->status(), 'error' => $errorMsg]);
                return ['error' => 'Failed to fetch models from OpenAI: ' . $errorMsg];
            }

            $models = $response->json('data', []);
            $formattedModels = [];

            foreach ($models as $model) {
                $id = $model['id'] ?? '';
                if (empty($id)) continue;

                // Include all chat-capable model families
                if (
                    str_starts_with($id, 'gpt-') ||
                    str_starts_with($id, 'o1-') ||
                    str_starts_with($id, 'o3-') ||
                    str_starts_with($id, 'o4-') ||
                    str_starts_with($id, 'chatgpt-')
                ) {
                    $formattedModels[$id] = $this->formatModelName($id);
                }
            }

            // Sort alphabetically by display name
            asort($formattedModels);

            return $formattedModels;

        } catch (\Exception $e) {
            Log::error('OpenAI model fetch exception', ['message' => $e->getMessage()]);
            return ['error' => 'Connection error while fetching OpenAI models: ' . $e->getMessage()];
        }
    }

    /**
     * Generate a human-friendly display name from a model ID.
     */
    private function formatModelName(string $id): string
    {
        $knownNames = [
            'gpt-4o' => 'GPT-4o (Flagship Multimodal)',
            'gpt-4o-mini' => 'GPT-4o Mini (Fast & Affordable)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'o1-preview' => 'o1 Preview (Reasoning)',
            'o1-mini' => 'o1 Mini (Reasoning)',
            'o3-mini' => 'o3 Mini (Reasoning)',
            'chatgpt-4o-latest' => 'ChatGPT-4o Latest',
        ];

        if (isset($knownNames[$id])) {
            return $knownNames[$id];
        }

        // Auto-format unknown models
        $name = str_replace(['-', '_'], ' ', $id);
        $name = ucwords($name);
        return $name;
    }
}
