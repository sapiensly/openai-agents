<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\State;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Class PersistentConversationStateManager
 *
 * Enhanced conversation state manager with persistence and advanced features.
 * Provides robust state management for conversations across handoffs.
 */
class PersistentConversationStateManager implements ConversationStateManager
{
    /**
     * The storage driver to use (cache, database, etc.).
     *
     * @var string
     */
    private string $storageDriver;

    /**
     * The TTL (time to live) for conversation data in seconds.
     *
     * @var int
     */
    private int $ttl;

    /**
     * Whether to enable compression for large state data.
     *
     * @var bool
     */
    private bool $enableCompression;

    /**
     * Create a new PersistentConversationStateManager instance.
     *
     * @param string $storageDriver The storage driver to use
     * @param int $ttl The TTL for conversation data in seconds
     * @param bool $enableCompression Whether to enable compression
     */
    public function __construct(
        string $storageDriver = 'cache',
        int $ttl = 86400, // 24 hours
        bool $enableCompression = true
    ) {
        $this->storageDriver = $storageDriver;
        $this->ttl = $ttl;
        $this->enableCompression = $enableCompression;
    }

    /**
     * {@inheritdoc}
     */
    public function saveContext(string $conversationId, array $context): void
    {
        $key = $this->getContextKey($conversationId);
        $data = [
            'conversation_id' => $conversationId,
            'context' => $context,
            'timestamp' => time(),
            'version' => '1.0'
        ];

        $this->store($key, $data);
        Log::info("[PersistentConversationStateManager] Saved context for conversation: {$conversationId}");
    }

    /**
     * {@inheritdoc}
     */
    public function loadContext(string $conversationId): array
    {
        $key = $this->getContextKey($conversationId);
        $data = $this->retrieve($key);

        if ($data && isset($data['context'])) {
            Log::info("[PersistentConversationStateManager] Loaded context for conversation: {$conversationId}");
            return $data['context'];
        }

        Log::info("[PersistentConversationStateManager] No context found for conversation: {$conversationId}");
        return [];
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
            'context_size' => count($context),
            'handoff_id' => $this->generateHandoffId()
        ];

        $this->addHandoffToHistory($conversationId, $handoffRecord);

        // Update conversation metadata
        $this->updateConversationMetadata($conversationId, $sourceAgentId, $targetAgentId);

        Log::info("[PersistentConversationStateManager] Saved handoff state: {$sourceAgentId} -> {$targetAgentId}");
    }

    /**
     * {@inheritdoc}
     */
    public function getConversationHistory(string $conversationId): array
    {
        $key = $this->getHistoryKey($conversationId);
        $data = $this->retrieve($key);

        return $data['messages'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getHandoffHistory(string $conversationId): array
    {
        $key = $this->getHandoffHistoryKey($conversationId);
        $data = $this->retrieve($key);

        return $data['handoffs'] ?? [];
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
        $key = $this->getHistoryKey($conversationId);
        $data = $this->retrieve($key);

        if (!isset($data['messages'])) {
            $data['messages'] = [];
        }

        $data['messages'][] = array_merge($message, [
            'timestamp' => time(),
            'message_id' => $this->generateMessageId()
        ]);

        $this->store($key, $data);
    }

    /**
     * Get conversation metadata.
     *
     * @param string $conversationId The conversation ID
     * @return array The conversation metadata
     */
    public function getConversationMetadata(string $conversationId): array
    {
        $key = $this->getMetadataKey($conversationId);
        $data = $this->retrieve($key);

        return $data ?? [];
    }

    /**
     * Get the current agent for a conversation.
     *
     * @param string $conversationId The conversation ID
     * @return string|null The current agent ID, or null if not found
     */
    public function getCurrentAgent(string $conversationId): ?string
    {
        $metadata = $this->getConversationMetadata($conversationId);
        return $metadata['current_agent'] ?? null;
    }

    /**
     * Get conversation statistics.
     *
     * @param string $conversationId The conversation ID
     * @return array The conversation statistics
     */
    public function getConversationStats(string $conversationId): array
    {
        $handoffHistory = $this->getHandoffHistory($conversationId);
        $conversationHistory = $this->getConversationHistory($conversationId);
        $metadata = $this->getConversationMetadata($conversationId);

        return [
            'total_handoffs' => count($handoffHistory),
            'total_messages' => count($conversationHistory),
            'conversation_duration' => $this->calculateConversationDuration($metadata),
            'current_agent' => $metadata['current_agent'] ?? 'unknown',
            'last_activity' => $metadata['last_activity'] ?? 0,
            'handoff_pattern' => $this->analyzeHandoffPattern($handoffHistory)
        ];
    }

    /**
     * Clear all data for a conversation.
     *
     * @param string $conversationId The conversation ID
     * @return void
     */
    public function clearConversation(string $conversationId): void
    {
        $keys = [
            $this->getContextKey($conversationId),
            $this->getHistoryKey($conversationId),
            $this->getHandoffHistoryKey($conversationId),
            $this->getMetadataKey($conversationId)
        ];

        foreach ($keys as $key) {
            $this->delete($key);
        }

        Log::info("[PersistentConversationStateManager] Cleared conversation: {$conversationId}");
    }

    /**
     * Clear all data for all conversations.
     *
     * @return void
     */
    public function clearAll(): void
    {
        // This would need to be implemented based on the storage driver
        // For cache-based storage, we can't easily clear all without knowing all keys
        Log::warning("[PersistentConversationStateManager] Clear all not implemented for this storage driver");
    }

    /**
     * Export conversation data.
     *
     * @param string $conversationId The conversation ID
     * @return array The exported conversation data
     */
    public function exportConversation(string $conversationId): array
    {
        return [
            'conversation_id' => $conversationId,
            'metadata' => $this->getConversationMetadata($conversationId),
            'context' => $this->loadContext($conversationId),
            'history' => $this->getConversationHistory($conversationId),
            'handoff_history' => $this->getHandoffHistory($conversationId),
            'stats' => $this->getConversationStats($conversationId),
            'exported_at' => time()
        ];
    }

    /**
     * Get the context storage key.
     *
     * @param string $conversationId The conversation ID
     * @return string The storage key
     */
    private function getContextKey(string $conversationId): string
    {
        return "conversation:{$conversationId}:context";
    }

    /**
     * Get the history storage key.
     *
     * @param string $conversationId The conversation ID
     * @return string The storage key
     */
    private function getHistoryKey(string $conversationId): string
    {
        return "conversation:{$conversationId}:history";
    }

    /**
     * Get the handoff history storage key.
     *
     * @param string $conversationId The conversation ID
     * @return string The storage key
     */
    private function getHandoffHistoryKey(string $conversationId): string
    {
        return "conversation:{$conversationId}:handoffs";
    }

    /**
     * Get the metadata storage key.
     *
     * @param string $conversationId The conversation ID
     * @return string The storage key
     */
    private function getMetadataKey(string $conversationId): string
    {
        return "conversation:{$conversationId}:metadata";
    }

    /**
     * Store data using the configured storage driver.
     *
     * @param string $key The storage key
     * @param array $data The data to store
     * @return void
     */
    private function store(string $key, array $data): void
    {
        if ($this->enableCompression && strlen(json_encode($data)) > 1024) {
            $data = gzcompress(json_encode($data));
        }

        Cache::put($key, $data, $this->ttl);
    }

    /**
     * Retrieve data using the configured storage driver.
     *
     * @param string $key The storage key
     * @return array|null The retrieved data, or null if not found
     */
    private function retrieve(string $key): ?array
    {
        $data = Cache::get($key);

        if ($data === null) {
            return null;
        }

        // Check if data is compressed
        if (is_string($data) && $this->isCompressed($data)) {
            $data = json_decode(gzuncompress($data), true);
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Delete data using the configured storage driver.
     *
     * @param string $key The storage key
     * @return void
     */
    private function delete(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Check if data is compressed.
     *
     * @param string $data The data to check
     * @return bool True if data is compressed
     */
    private function isCompressed(string $data): bool
    {
        // Simple heuristic to detect compressed data
        return strlen($data) > 0 && ord($data[0]) === 0x1f && ord($data[1]) === 0x8b;
    }

    /**
     * Add a handoff record to the history.
     *
     * @param string $conversationId The conversation ID
     * @param array $handoffRecord The handoff record
     * @return void
     */
    private function addHandoffToHistory(string $conversationId, array $handoffRecord): void
    {
        $key = $this->getHandoffHistoryKey($conversationId);
        $data = $this->retrieve($key);

        if (!isset($data['handoffs'])) {
            $data['handoffs'] = [];
        }

        $data['handoffs'][] = $handoffRecord;

        $this->store($key, $data);
    }

    /**
     * Update conversation metadata.
     *
     * @param string $conversationId The conversation ID
     * @param string $sourceAgentId The source agent ID
     * @param string $targetAgentId The target agent ID
     * @return void
     */
    private function updateConversationMetadata(string $conversationId, string $sourceAgentId, string $targetAgentId): void
    {
        $key = $this->getMetadataKey($conversationId);
        $data = $this->retrieve($key) ?? [];

        $data['current_agent'] = $targetAgentId;
        $data['last_activity'] = time();
        $data['total_handoffs'] = ($data['total_handoffs'] ?? 0) + 1;
        $data['last_handoff'] = [
            'source' => $sourceAgentId,
            'target' => $targetAgentId,
            'timestamp' => time()
        ];

        $this->store($key, $data);
    }

    /**
     * Generate a unique handoff ID.
     *
     * @return string The generated handoff ID
     */
    private function generateHandoffId(): string
    {
        return 'ho_' . uniqid('', true);
    }

    /**
     * Generate a unique message ID.
     *
     * @return string The generated message ID
     */
    private function generateMessageId(): string
    {
        return 'msg_' . uniqid('', true);
    }

    /**
     * Calculate conversation duration.
     *
     * @param array $metadata The conversation metadata
     * @return int The duration in seconds
     */
    private function calculateConversationDuration(array $metadata): int
    {
        $startTime = $metadata['created_at'] ?? time();
        $lastActivity = $metadata['last_activity'] ?? time();

        return $lastActivity - $startTime;
    }

    /**
     * Analyze handoff pattern.
     *
     * @param array $handoffHistory The handoff history
     * @return array The handoff pattern analysis
     */
    private function analyzeHandoffPattern(array $handoffHistory): array
    {
        if (empty($handoffHistory)) {
            return ['pattern' => 'none', 'cycles' => 0];
        }

        $pattern = [];
        $cycles = 0;

        foreach ($handoffHistory as $handoff) {
            $pattern[] = $handoff['source_agent'] . ' -> ' . $handoff['target_agent'];
        }

        // Detect cycles (A -> B -> A)
        for ($i = 0; $i < count($pattern) - 1; $i++) {
            if ($pattern[$i] === $pattern[$i + 1]) {
                $cycles++;
            }
        }

        return [
            'pattern' => implode(', ', $pattern),
            'cycles' => $cycles,
            'total_handoffs' => count($pattern)
        ];
    }
} 