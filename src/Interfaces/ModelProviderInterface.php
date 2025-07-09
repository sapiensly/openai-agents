<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Interfaces;

/**
 * Interface ModelProviderInterface
 *
 * Defines the contract for model providers that can be used with the OpenAI Agents package.
 * This allows for easy switching between different AI model providers (OpenAI, Anthropic, Google, etc.).
 */
interface ModelProviderInterface
{
    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the provider version.
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get available models for this provider.
     *
     * @return array
     */
    public function getAvailableModels(): array;

    /**
     * Check if a model is available.
     *
     * @param string $model The model name
     * @return bool
     */
    public function isModelAvailable(string $model): bool;

    /**
     * Get model information.
     *
     * @param string $model The model name
     * @return array|null
     */
    public function getModelInfo(string $model): ?array;

    /**
     * Create a chat completion.
     *
     * @param array $messages The messages
     * @param array $options The options
     * @return array
     */
    public function createChatCompletion(array $messages, array $options = []): array;

    /**
     * Create a streaming chat completion.
     *
     * @param array $messages The messages
     * @param array $options The options
     * @return \Generator
     */
    public function createStreamingChatCompletion(array $messages, array $options = []): \Generator;

    /**
     * Transcribe audio to text.
     *
     * @param string $file The audio file path
     * @param array $options The options
     * @return string
     */
    public function transcribeAudio(string $file, array $options = []): string;

    /**
     * Generate speech from text.
     *
     * @param string $text The text to convert
     * @param array $options The options
     * @return string
     */
    public function generateSpeech(string $text, array $options = []): string;

    /**
     * Get the provider configuration.
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * Test the provider connection.
     *
     * @return bool
     */
    public function testConnection(): bool;

    /**
     * Get provider statistics.
     *
     * @return array
     */
    public function getStats(): array;
} 