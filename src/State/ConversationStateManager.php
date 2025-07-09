<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\State;

/**
 * Interface ConversationStateManager
 *
 * Defines methods for managing conversation state, including context and handoff history.
 */
interface ConversationStateManager
{
    /**
     * Save context data for a conversation.
     *
     * @param string $conversationId The ID of the conversation
     * @param array $context The context data to save
     * @return void
     */
    public function saveContext(string $conversationId, array $context): void;

    /**
     * Load context data for a conversation.
     *
     * @param string $conversationId The ID of the conversation
     * @return array The context data
     */
    public function loadContext(string $conversationId): array;

    /**
     * Save handoff state for a conversation.
     *
     * @param string $conversationId The ID of the conversation
     * @param string $sourceAgentId The ID of the source agent
     * @param string $targetAgentId The ID of the target agent
     * @param array $context The context data to save
     * @return void
     */
    public function saveHandoffState(
        string $conversationId,
        string $sourceAgentId,
        string $targetAgentId,
        array $context
    ): void;

    /**
     * Get the conversation history for a conversation.
     *
     * @param string $conversationId The ID of the conversation
     * @return array The conversation history
     */
    public function getConversationHistory(string $conversationId): array;

    /**
     * Get the handoff history for a conversation.
     *
     * @param string $conversationId The ID of the conversation
     * @return array The handoff history
     */
    public function getHandoffHistory(string $conversationId): array;
}
