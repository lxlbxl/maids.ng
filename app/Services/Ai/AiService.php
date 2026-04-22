<?php

namespace App\Services\Ai;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Get the active AI provider based on system settings.
     */
    public function getProvider(): AiProvider
    {
        $providerKey = Setting::get('ai_active_provider', 'openai');

        return match ($providerKey) {
            'openrouter' => new OpenRouterDriver(),
            default => new OpenAiDriver(),
        };
    }

    /**
     * Centralized chat method that handles the routing to the active provider.
     */
    public function chat(string $prompt, array $options = []): array
    {
        try {
            return $this->getProvider()->chat($prompt, $options);
        } catch (\Exception $e) {
            Log::error("AiService Error: " . $e->getMessage());
            return [
                'error' => 'System Intelligence Failure',
                'message' => 'The AI provider is currently unreachable. Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all providers and their respective models for configuration UI.
     */
    public function getProviderManifest(): array
    {
        return [
            'openai' => [
                'name' => 'OpenAI',
                'models' => (new OpenAiDriver())->getModels(),
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'models' => (new OpenRouterDriver())->getModels(),
            ],
        ];
    }
}
