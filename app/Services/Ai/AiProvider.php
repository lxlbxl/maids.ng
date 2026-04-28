<?php

namespace App\Services\Ai;

interface AiProvider
{
    /**
     * Send a prompt to the AI model and return the response.
     */
    public function chat(string $prompt, array $options = []): array;

    /**
     * Get the list of available models for this provider.
     */
    public function getModels(): array;
}
