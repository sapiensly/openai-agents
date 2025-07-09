<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\State;

use Illuminate\Redis\Connections\Connection as RedisConnection;

/**
 * Class RedisConversationStateManager
 *
 * Redis implementation of the ConversationStateManager interface.
 * Uses Redis to store and retrieve conversation state data.
 */
class RedisConversationStateManager implements ConversationStateManager
{
    /**
     * The Redis connection.
     *
     * @var RedisConnection
     */
    private RedisConnection $redis;

    /**
     * The key prefix for Redis keys.
     *
     * @var string
     */
    private string $keyPrefix;

    /**
     * The TTL (time to live) for Redis keys in seconds.
     *
     * @var int
     */
    private int $ttl;

    /**
     * Create a new RedisConversationStateManager instance.
     *
     * @param RedisConnection $redis The Redis connection
     * @param string $keyPrefix The prefix for Redis keys
     * @param int $ttl The TTL (time to live) for Redis keys in seconds
     */
    public function __construct(
        RedisConnection $redis,
        string $keyPrefix = 'agent:conv:',
        int $ttl = 86400
    ) {
        $this->redis = $redis;
        $this->keyPrefix = $keyPrefix;
        $this->ttl = $ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function saveContext(string $conversationId, array $context): void
    {
        $key = $this->keyPrefix . $conversationId . ':context';
        $this->redis->set($key, json_encode($context));
        $this->redis->expire($key, $this->ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function loadContext(string $conversationId): array
    {
        $key = $this->keyPrefix . $conversationId . ':context';
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function saveHandoffState(
        string $conversationId,
        string $sourceAgentId,
        string $targetAgentId,
        array $context
    ): void {
        // Save the context
        $this->saveContext($conversationId, $context);

        // Record the handoff in the history
        $handoffRecord = [
            'timestamp' => time(),
            'source_agent' => $sourceAgentId,
            'target_agent' => $targetAgentId,
            'context_size' => count($context)
        ];

        $key = $this->keyPrefix . $conversationId . ':handoffs';
        $this->redis->rPush($key, json_encode($handoffRecord));
        $this->redis->expire($key, $this->ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function getConversationHistory(string $conversationId): array
    {
        $key = $this->keyPrefix . $conversationId . ':messages';
        $messages = $this->redis->lRange($key, 0, -1);
        return array_map(fn($m) => json_decode($m, true), $messages);
    }

    /**
     * {@inheritdoc}
     */
    public function getHandoffHistory(string $conversationId): array
    {
        $key = $this->keyPrefix . $conversationId . ':handoffs';
        $handoffs = $this->redis->lRange($key, 0, -1);
        return array_map(fn($h) => json_decode($h, true), $handoffs);
    }

    /**
     * Save a message to the conversation history.
     *
     * @param string $conversationId The ID of the conversation
     * @param array $message The message to save
     * @return void
     */
    public function saveMessage(string $conversationId, array $message): void
    {
        $key = $this->keyPrefix . $conversationId . ':messages';
        $this->redis->rPush($key, json_encode($message));
        $this->redis->expire($key, $this->ttl);
    }

    /**
     * Clear all data for a conversation.
     *
     * @param string $conversationId The ID of the conversation
     * @return void
     */
    public function clearConversation(string $conversationId): void
    {
        $keys = [
            $this->keyPrefix . $conversationId . ':context',
            $this->keyPrefix . $conversationId . ':messages',
            $this->keyPrefix . $conversationId . ':handoffs'
        ];

        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }
}
