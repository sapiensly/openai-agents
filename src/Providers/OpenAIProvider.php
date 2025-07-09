<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Providers;

use Sapiensly\OpenaiAgents\Interfaces\ModelProviderInterface;
use OpenAI\Factory;
use OpenAI\Contracts\ClientContract;
use Illuminate\Support\Facades\Log;

/**
 * Class OpenAIProvider
 *
 * OpenAI implementation of the ModelProviderInterface.
 */
class OpenAIProvider implements ModelProviderInterface
{
    /**
     * The OpenAI client.
     */
    private ClientContract $client;

    /**
     * The provider configuration.
     */
    private array $config;

    /**
     * Provider statistics.
     */
    private array $stats = [
        'requests' => 0,
        'tokens_used' => 0,
        'errors' => 0,
        'last_request' => null,
    ];

    /**
     * Create a new OpenAIProvider instance.
     *
     * @param array $config The provider configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->client = (new Factory())->withApiKey($config['api_key'] ?? '')->make();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'OpenAI';
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableModels(): array
    {
        return [
            'gpt-4o' => [
                'name' => 'GPT-4o',
                'type' => 'chat',
                'max_tokens' => 128000,
                'supports_streaming' => true,
            ],
            'gpt-4o-mini' => [
                'name' => 'GPT-4o Mini',
                'type' => 'chat',
                'max_tokens' => 128000,
                'supports_streaming' => true,
            ],
            'gpt-3.5-turbo' => [
                'name' => 'GPT-3.5 Turbo',
                'type' => 'chat',
                'max_tokens' => 16385,
                'supports_streaming' => true,
            ],
            'whisper-1' => [
                'name' => 'Whisper',
                'type' => 'audio',
                'supports_streaming' => false,
            ],
            'tts-1' => [
                'name' => 'Text-to-Speech',
                'type' => 'audio',
                'supports_streaming' => false,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isModelAvailable(string $model): bool
    {
        $availableModels = $this->getAvailableModels();
        return isset($availableModels[$model]);
    }

    /**
     * {@inheritdoc}
     */
    public function getModelInfo(string $model): ?array
    {
        $availableModels = $this->getAvailableModels();
        return $availableModels[$model] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function createChatCompletion(array $messages, array $options = []): array
    {
        $this->stats['requests']++;
        $this->stats['last_request'] = now();

        try {
            $params = array_merge([
                'model' => $options['model'] ?? 'gpt-4o',
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? null,
                'top_p' => $options['top_p'] ?? 1.0,
                'frequency_penalty' => $options['frequency_penalty'] ?? 0.0,
                'presence_penalty' => $options['presence_penalty'] ?? 0.0,
            ], $options);

            $response = $this->client->chat()->create($params);

            // Update stats
            if (isset($response->usage)) {
                $this->stats['tokens_used'] += $response->usage->totalTokens;
            }

            return [
                'content' => $response->choices[0]->message->content,
                'finish_reason' => $response->choices[0]->finishReason,
                'usage' => $response->usage ? [
                    'prompt_tokens' => $response->usage->promptTokens,
                    'completion_tokens' => $response->usage->completionTokens,
                    'total_tokens' => $response->usage->totalTokens,
                ] : null,
            ];

        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error("[OpenAIProvider] Chat completion error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamingChatCompletion(array $messages, array $options = []): \Generator
    {
        $this->stats['requests']++;
        $this->stats['last_request'] = now();

        try {
            $params = array_merge([
                'model' => $options['model'] ?? 'gpt-4o',
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? null,
                'top_p' => $options['top_p'] ?? 1.0,
                'frequency_penalty' => $options['frequency_penalty'] ?? 0.0,
                'presence_penalty' => $options['presence_penalty'] ?? 0.0,
                'stream' => true,
            ], $options);

            $stream = $this->client->chat()->createStreamed($params);

            foreach ($stream as $response) {
                if (isset($response->choices[0]->delta->content)) {
                    yield $response->choices[0]->delta->content;
                }
            }

        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error("[OpenAIProvider] Streaming chat completion error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function transcribeAudio(string $file, array $options = []): string
    {
        $this->stats['requests']++;
        $this->stats['last_request'] = now();

        try {
            $params = array_merge([
                'model' => 'whisper-1',
                'file' => fopen($file, 'r'),
                'response_format' => 'text',
            ], $options);

            $response = $this->client->audio()->transcribe($params);
            return $response->text;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error("[OpenAIProvider] Audio transcription error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateSpeech(string $text, array $options = []): string
    {
        $this->stats['requests']++;
        $this->stats['last_request'] = now();

        try {
            $params = array_merge([
                'model' => 'tts-1',
                'input' => $text,
                'voice' => 'alloy',
            ], $options);

            return $this->client->audio()->speech($params);

        } catch (\Exception $e) {
            $this->stats['errors']++;
            Log::error("[OpenAIProvider] Speech generation error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): bool
    {
        try {
            $this->client->models()->list();
            return true;
        } catch (\Exception $e) {
            Log::error("[OpenAIProvider] Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(): array
    {
        return $this->stats;
    }
} 