<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Stores;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sapiensly\OpenaiAgents\Persistence\Contracts\ConversationStore;
use Sapiensly\OpenaiAgents\Persistence\Models\Conversation;
use Sapiensly\OpenaiAgents\Persistence\Models\Message;

/**
 * Class DatabaseStore
 *
 * Handles operations for managing conversations and messages, leveraging database and cache mechanisms.
 */
class DatabaseStore implements ConversationStore
{
    protected string $cachePrefix = 'agent:conv:';
    protected int $cacheTtl = 3600;

    /**
     * Set $cacheTtl from config or default to 3600 seconds (1 hour).
     * This value determines how long cached data will be stored before it expires.
     */
    public function __construct()
    {
        $this->cacheTtl = (int) (config('sapiensly-openai-agents.persistence.stores.database.cache.ttl') ?? 3600);
    }

    protected function isCacheEnabled(): bool
    {
        return (bool) (config('sapiensly-openai-agents.persistence.stores.database.cache.enabled') ?? true);
    }

    /**
     * Finds an existing conversation by its ID or creates a new one if it does not exist.
     *
     * @param string $id The unique identifier for the conversation.
     * @param array|null $metadata Optional metadata to associate with the conversation.
     *                              Includes agent_id, user_id, and other related information.
     *
     * @return array The conversation data as an associative array.
     */
    public function findOrCreate(string $id, ?array $metadata = []): array
    {
        $conversation = Conversation::firstOrCreate(
            ['id' => $id],
            [
                'agent_id' => $metadata['agent_id'] ?? null,
                'user_id' => auth()->id() ?? null,
                'metadata' => $metadata ?? [],
            ]
        );
        return $conversation->toArray();
    }

    /**
     * Adds a new message to the specified conversation.
     *
     * Performs a database transaction to create a new message and update the associated
     * conversation's details, such as the last message timestamp, message count, and total tokens.
     *
     * If caching is enabled, the cache for the specified conversation is flushed.
     *
     * @param string $conversationId The ID of the conversation to which the message belongs.
     * @param array $message An associative array containing message properties, including:
     *                       - role (string|null): The role associated with the message.
     *                       - content (string|null): The content of the message.
     *                       - token_count (int|null): The token count of the message.
     *                       - metadata (array|null): Additional metadata for the message.
     *
     * @return void
     */
    public function addMessage(string $conversationId, array $message): void
    {
        DB::transaction(function () use ($conversationId, $message) {
            Message::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'role' => (string) ($message['role'] ?? ''),
                'content' => (string) ($message['content'] ?? ''),
                'token_count' => $message['token_count'] ?? null,
                'metadata' => $message['metadata'] ?? null,
                'created_at' => now(),
            ]);

            Conversation::where('id', $conversationId)->update([
                'last_message_at' => now(),
                'message_count' => DB::raw('message_count + 1'),
                'total_tokens' => DB::raw('total_tokens + ' . (int) ($message['token_count'] ?? 0)),
            ]);
        });

        if ($this->isCacheEnabled()) {
            $this->flushCache($conversationId);
        }
    }

    /**
     * Retrieves the most recent messages for a given conversation.
     *
     * This method retrieves messages from the specified conversation ID, either directly from
     * the database or from the cache, if caching is enabled. The messages are ordered by their
     * creation timestamp in descending order and returned in reverse order to maintain the
     * chronological sequence.
     *
     * Caching behavior is influenced by the application's settings. When enabled, messages are
     * cached for a specified period, using a unique cache key. Tag-based caching is supported
     * if the cache store allows it.
     *
     * @param string $conversationId The unique identifier of the conversation.
     * @param int $limit The maximum number of recent messages to retrieve. Defaults to 20.
     *
     * @return array An array of messages, where each message contains:
     *               - role (string|null): The role associated with the message.
     *               - content (string|null): The content of the message.
     *               - token_count (int|null): The token count of the message.
     *               - metadata (array|null): Additional metadata for the message.
     *               - created_at (string|null): The ISO 8601 timestamp of message creation.
     */
    public function getRecentMessages(string $conversationId, int $limit = 20): array
    {
        if (!$this->isCacheEnabled()) {
            return Message::where('conversation_id', $conversationId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values()
                ->map(function ($m) {
                    return [
                        'role' => $m->role,
                        'content' => $m->content,
                        'token_count' => $m->token_count,
                        'metadata' => $m->metadata,
                        'created_at' => $m->created_at?->toISOString(),
                    ];
                })
                ->toArray();
        }

        $key = $this->cachePrefix . $conversationId . ':messages:' . $limit;
        $useTags = (bool) (config('sapiensly-openai-agents.persistence.stores.database.cache.tags') ?? true);

        $remember = function () use ($conversationId, $limit) {
            return Message::where('conversation_id', $conversationId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values()
                ->map(function ($m) {
                    return [
                        'role' => $m->role,
                        'content' => $m->content,
                        'token_count' => $m->token_count,
                        'metadata' => $m->metadata,
                        'created_at' => $m->created_at?->toISOString(),
                    ];
                })
                ->toArray();
        };

        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            return Cache::tags(['conversation:' . $conversationId])->remember($key, $this->cacheTtl, $remember);
        }

        return Cache::remember($key, $this->cacheTtl, $remember);
    }

    /**
     * Retrieves the summary of the specified conversation.
     *
     * If caching is enabled, it attempts to fetch the summary from cache. If not present in cache,
     * it queries the database and stores the result in the cache. Supports caching with tag functionality
     * if the cache driver supports it.
     *
     * @param string $conversationId The ID of the conversation whose summary is being retrieved.
     *
     * @return string|null The summary of the conversation, or null if not found.
     */
    public function getSummary(string $conversationId): ?string
    {
        if (!$this->isCacheEnabled()) {
            return Conversation::find($conversationId)?->summary;
        }

        $key = $this->cachePrefix . $conversationId . ':summary';
        $useTags = (bool) (config('sapiensly-openai-agents.persistence.stores.database.cache.tags') ?? true);

        $remember = function () use ($conversationId) {
            return Conversation::find($conversationId)?->summary;
        };

        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            return Cache::tags(['conversation:' . $conversationId])->remember($key, $this->cacheTtl, $remember);
        }

        return Cache::remember($key, $this->cacheTtl, $remember);
    }

    /**
     * Updates the summary of the specified conversation.
     *
     * Updates the conversation's summary and the timestamp indicating when
     * the summary was last updated. If caching is enabled, the cache for the
     * specified conversation is flushed.
     *
     * @param string $conversationId The ID of the conversation to update.
     * @param string $summary The updated summary content for the conversation.
     *
     * @return void
     */
    public function updateSummary(string $conversationId, string $summary): void
    {
        Conversation::where('id', $conversationId)->update([
            'summary' => $summary,
            'summary_updated_at' => now(),
        ]);

        if ($this->isCacheEnabled()) {
            $this->flushCache($conversationId);
        }
    }

    /**
     * Deletes a conversation and all associated messages.
     *
     * Performs a database transaction to delete all messages belonging to the specified conversation
     * and subsequently removes the conversation itself.
     *
     * If caching is enabled, clears the cache for the specified conversation.
     *
     * @param string $conversationId The ID of the conversation to be deleted, along with its messages.
     *
     * @return void
     */
    public function delete(string $conversationId): void
    {
        DB::transaction(function () use ($conversationId) {
            Message::where('conversation_id', $conversationId)->delete();
            Conversation::where('id', $conversationId)->delete();
        });

        if ($this->isCacheEnabled()) {
            $this->flushCache($conversationId);
        }
    }

    /**
     * Flushes the cache for a given conversation.
     *
     * Clears the cached data associated with the specified conversation ID based on the configured cache settings.
     * If tag-based caching is supported and enabled, it uses cache tags to flush the relevant entries. Otherwise,
     * it falls back to manually removing commonly used cache keys relevant to the conversation.
     *
     * @param string $conversationId The ID of the conversation whose cache should be flushed.
     *
     * @return void
     */
    protected function flushCache(string $conversationId): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $useTags = (bool) (config('sapiensly-openai-agents.persistence.stores.database.cache.tags') ?? true);

        if ($useTags && method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['conversation:' . $conversationId])->flush();
        } else {
            // Fallback manual - solo keys más comunes
            $keys = [
                $this->cachePrefix . $conversationId . ':summary',
                $this->cachePrefix . $conversationId . ':messages:10',
                $this->cachePrefix . $conversationId . ':messages:20',
                $this->cachePrefix . $conversationId . ':messages:50',
                $this->cachePrefix . $conversationId . ':messages:100',
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }
}
