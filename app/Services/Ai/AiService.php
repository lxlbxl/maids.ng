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
     * Returns the hardcoded fallback models (used as initial page data).
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

    /**
     * Fetch models dynamically from the provider's API.
     * Returns ['models' => [...]] on success, or ['error' => '...'] on failure.
     */
    public function fetchModels(string $provider): array
    {
        try {
            $driver = match ($provider) {
                'openai' => new OpenAiDriver(),
                'openrouter' => new OpenRouterDriver(),
                default => null,
            };

            if (!$driver) {
                return ['error' => "Unknown provider: {$provider}. Supported providers: openai, openrouter"];
            }

            if (!method_exists($driver, 'fetchModelsFromApi')) {
                return ['error' => "Provider '{$provider}' does not support dynamic model fetching"];
            }

            $result = $driver->fetchModelsFromApi();

            // If the result has an 'error' key, it's an error response from the driver
            if (isset($result['error'])) {
                return $result;
            }

            // Otherwise the result IS the models array
            return ['models' => $result];

        } catch (\Exception $e) {
            Log::error("AiService fetchModels error for provider {$provider}", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['error' => 'Unexpected error fetching models: ' . $e->getMessage()];
        }
    }
}
