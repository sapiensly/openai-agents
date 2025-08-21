<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Providers;

use Sapiensly\OpenaiAgents\Interfaces\ModelProviderInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Class ModelProviderManager
 *
 * Manages multiple model providers and allows easy switching between them.
 */
class ModelProviderManager
{
    /**
     * The registered providers.
     */
    private array $providers = [];

    /**
     * The default provider.
     */
    private string $defaultProvider = 'openai';

    /**
     * The current provider.
     */
    private string $currentProvider;

    /**
     * Create a new ModelProviderManager instance.
     *
     * @param array $config The configuration
     */
    public function __construct(array $config = [])
    {
        $this->currentProvider = $config['default'] ?? 'openai';
        $this->registerDefaultProviders();
    }

    /**
     * Register the default providers.
     */
    private function registerDefaultProviders(): void
    {
        // Register OpenAI provider
        $this->registerProvider('openai', new OpenAIProvider([
            'api_key' => Config::get('sapiensly-openai-agents.api_key'),
            'organization' => Config::get('sapiensly-openai-agents.organization'),
        ]));
    }

    /**
     * Register a provider.
     *
     * @param string $name The provider name
     * @param ModelProviderInterface $provider The provider instance
     * @return self
     */
    public function registerProvider(string $name, ModelProviderInterface $provider): self
    {
        $this->providers[$name] = $provider;
        return $this;
    }

    /**
     * Get a provider.
     *
     * @param string $name The provider name
     * @return ModelProviderInterface|null
     */
    public function getProvider(string $name): ?ModelProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get the current provider.
     *
     * @return ModelProviderInterface
     */
    public function getCurrentProvider(): ModelProviderInterface
    {
        return $this->providers[$this->currentProvider] ??
               $this->providers[$this->defaultProvider] ??
               throw new \Exception("No provider available");
    }

    /**
     * Set the current provider.
     *
     * @param string $name The provider name
     * @return self
     */
    public function setCurrentProvider(string $name): self
    {
        if (!isset($this->providers[$name])) {
            throw new \Exception("Provider '{$name}' not found");
        }

        $this->currentProvider = $name;
        Log::info("[ModelProviderManager] Switched to provider: {$name}");
        return $this;
    }

    /**
     * Get all registered providers.
     *
     * @return array
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get provider names.
     *
     * @return array
     */
    public function getProviderNames(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Test all provider connections.
     *
     * @return array
     */
    public function testAllConnections(): array
    {
        $results = [];

        foreach ($this->providers as $name => $provider) {
            $results[$name] = [
                'name' => $provider->getName(),
                'version' => $provider->getVersion(),
                'connection' => $provider->testConnection(),
                'available_models' => count($provider->getAvailableModels()),
            ];
        }

        return $results;
    }

    /**
     * Get statistics from all providers.
     *
     * @return array
     */
    public function getAllStats(): array
    {
        $stats = [];

        foreach ($this->providers as $name => $provider) {
            $stats[$name] = $provider->getStats();
        }

        return $stats;
    }

    /**
     * Get available models from all providers.
     *
     * @return array
     */
    public function getAllAvailableModels(): array
    {
        $models = [];

        foreach ($this->providers as $name => $provider) {
            $models[$name] = $provider->getAvailableModels();
        }

        return $models;
    }

    /**
     * Find a model across all providers.
     *
     * @param string $model The model name
     * @return array|null Array with provider name and model info, or null if not found
     */
    public function findModel(string $model): ?array
    {
        foreach ($this->providers as $name => $provider) {
            if ($provider->isModelAvailable($model)) {
                return [
                    'provider' => $name,
                    'model' => $model,
                    'info' => $provider->getModelInfo($model),
                ];
            }
        }

        return null;
    }

    /**
     * Create a chat completion using the current provider.
     *
     * @param array $messages The messages
     * @param array $options The options
     * @return array
     */
    public function createChatCompletion(array $messages, array $options = []): array
    {
        return $this->getCurrentProvider()->createChatCompletion($messages, $options);
    }

    /**
     * Create a streaming chat completion using the current provider.
     *
     * @param array $messages The messages
     * @param array $options The options
     * @return \Generator
     */
    public function createStreamingChatCompletion(array $messages, array $options = []): \Generator
    {
        return $this->getCurrentProvider()->createStreamingChatCompletion($messages, $options);
    }

    /**
     * Transcribe audio using the current provider.
     *
     * @param string $file The audio file path
     * @param array $options The options
     * @return string
     */
    public function transcribeAudio(string $file, array $options = []): string
    {
        return $this->getCurrentProvider()->transcribeAudio($file, $options);
    }

    /**
     * Generate speech using the current provider.
     *
     * @param string $text The text to convert
     * @param array $options The options
     * @return string
     */
    public function generateSpeech(string $text, array $options = []): string
    {
        return $this->getCurrentProvider()->generateSpeech($text, $options);
    }
}
