<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\State;

/**
 * Class ArrayConversationStateManager
 *
 * In-memory implementation of the ConversationStateManager interface.
 * Uses arrays to store conversation state data. Useful for testing and development.
 */
class ArrayConversationStateManager implements ConversationStateManager
{
    /**
     * Array of context data, indexed by conversation ID.
     *
     * @var array<string, array>
     */
    private array $contexts = [];

    /**
     * Array of message histories, indexed by conversation ID.
     *
     * @var array<string, array>
     */
    private array $messages = [];

    /**
     * Array of handoff histories, indexed by conversation ID.
     *
     * @var array<string, array>
     */
    private array $handoffs = [];

    /**
     * {@inheritdoc}
     */
    public function saveContext(string $conversationId, array $context): void
    {
        $this->contexts[$conversationId] = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function loadContext(string $conversationId): array
    {
        return $this->contexts[$conversationId] ?? [];
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

        if (!isset($this->handoffs[$conversationId])) {
            $this->handoffs[$conversationId] = [];
        }

        $this->handoffs[$conversationId][] = $handoffRecord;
    }

    /**
     * {@inheritdoc}
     */
    public function getConversationHistory(string $conversationId): array
    {
        return $this->messages[$conversationId] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getHandoffHistory(string $conversationId): array
    {
        return $this->handoffs[$conversationId] ?? [];
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
        if (!isset($this->messages[$conversationId])) {
            $this->messages[$conversationId] = [];
        }

        $this->messages[$conversationId][] = $message;
    }

    /**
     * Clear all data for a conversation.
     *
     * @param string $conversationId The ID of the conversation
     * @return void
     */
    public function clearConversation(string $conversationId): void
    {
        unset($this->contexts[$conversationId]);
        unset($this->messages[$conversationId]);
        unset($this->handoffs[$conversationId]);
    }

    /**
     * Clear all data for all conversations.
     *
     * @return void
     */
    public function clearAll(): void
    {
        $this->contexts = [];
        $this->messages = [];
        $this->handoffs = [];
    }
}
