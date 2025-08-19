<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Contracts;

/**
 * ConversationStore defines a pluggable persistence interface for agent conversations.
 *
 * Implementations: DatabaseStore, CacheStore, NullStore.
 */
interface ConversationStore
{
    /**
     * Find an existing conversation or create a new one.
     *
     * @param string $id
     * @param array|null $metadata
     * @return array Conversation data as array
     */
    public function findOrCreate(string $id, ?array $metadata = []): array;

    /**
     * Append a message to the conversation.
     *
     * @param string $conversationId
     * @param array $message {role, content, token_count?, metadata?}
     */
    public function addMessage(string $conversationId, array $message): void;

    /**
     * Get recent messages for a conversation.
     *
     * @param string $conversationId
     * @param int $limit
     * @return array<int,array>
     */
    public function getRecentMessages(string $conversationId, int $limit = 20): array;

    /**
     * Get the stored summary for a conversation.
     */
    public function getSummary(string $conversationId): ?string;

    /**
     * Update the conversation summary.
     */
    public function updateSummary(string $conversationId, string $summary): void;

    /**
     * Delete a conversation and its messages.
     */
    public function delete(string $conversationId): void;
}
