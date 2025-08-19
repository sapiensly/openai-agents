<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence;

use Illuminate\Support\Str;
use Sapiensly\OpenaiAgents\Persistence\Contracts\ConversationStore;
use Sapiensly\OpenaiAgents\Persistence\Strategies\ContextStrategy;

/**
 * Trait that adds optional persistence to Agent without breaking defaults.
 */
trait PersistentAgentTrait
{
    protected ?string $conversationId = null;
    protected ?ConversationStore $persistenceStore = null;
    protected ?ContextStrategy $contextStrategy = null;
    protected bool $persistenceEnabled = false;
    protected bool $autoSummarize = true;
    protected int $summarizeAfter = 20;

    /**
     * Enable persistence for this agent with a conversation ID.
     */
    public function withConversation(?string $conversationId = null): self
    {
        $this->conversationId = $conversationId ?: (string) Str::uuid();
        $this->persistenceEnabled = (bool) (config('agent-persistence.enabled', true));

        if (!$this->persistenceStore) {
            // Resolve store from container; default binding should be NullStore
            $this->persistenceStore = app(ConversationStore::class);
        }
        if (!$this->contextStrategy) {
            $this->contextStrategy = app(ContextStrategy::class);
        }

        // Create conversation right away
        $this->persistenceStore->findOrCreate($this->conversationId, [
            'agent_id' => method_exists($this, 'getId') ? $this->getId() : null,
            'model' => $this->options->get('model') ?? null,
        ]);
        $this->loadPersistedContext();
        return $this;
    }

    /**
     * Get the current conversation ID or null.
     */
    public function getConversationId(): ?string
    {
        return $this->conversationId;
    }

    /**
     * Load persisted context/messages non-destructively.
     */
    protected function loadPersistedContext(): void
    {
        if (!$this->persistenceEnabled || !$this->conversationId) {
            return;
        }
        $messages = $this->persistenceStore->getRecentMessages($this->conversationId, (int) (config('agent-persistence.context.max_messages') ?? 20));
        $summary = $this->persistenceStore->getSummary($this->conversationId);
        $context = $this->contextStrategy->buildContext($messages, $summary);

        if ($context) {
            // Avoid overwriting developer/system prompts; add a contextual system note
            if (method_exists($this, 'appendInstructions')) {
                // Prefer instructions append if available
                $this->appendInstructions("\n\n[Persisted Context]\n" . $context);
            } else {
                $this->messages[] = ['role' => 'system', 'content' => $context];
            }
        }
        $maxTurns = (int) ($this->options->get('max_turns') ?? 10);
        $recentMessages = array_slice($messages, -($maxTurns * 2));
        foreach ($recentMessages as $msg) {
            if (!$this->messageExists($msg)) {
                $this->messages[] = [
                    'role' => (string) ($msg['role'] ?? ''),
                    'content' => (string) ($msg['content'] ?? ''),
                ];
            }
        }
    }

    protected function messageExists(array $message): bool
    {
        foreach ($this->messages as $existing) {
            if (($existing['role'] ?? null) === ($message['role'] ?? null)
                && ($existing['content'] ?? null) === ($message['content'] ?? null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Persist a message if enabled.
     */
    protected function persistMessage(string $role, string $content, array $metadata = []): void
    {
        if (!$this->persistenceEnabled || !$this->conversationId) {
            return;
        }
        $this->persistenceStore->addMessage($this->conversationId, [
            'role' => $role,
            'content' => $content,
            'token_count' => $metadata['token_count'] ?? null,
            'metadata' => $metadata,
        ]);
        $this->maybeSummarize();
    }

    /**
     * Minimal summarization trigger (deferred via afterResponse when available).
     */
    protected function maybeSummarize(): void
    {
        if (!$this->autoSummarize || !$this->persistenceEnabled || !$this->conversationId) {
            return;
        }
        $after = (int) (config('agent-persistence.summarization.after_messages') ?? $this->summarizeAfter);
        $messages = $this->persistenceStore->getRecentMessages($this->conversationId, 100);
        if (count($messages) >= $after && count($messages) % 10 === 0) {
            // For minimal implementation, we skip generating summary to avoid model dependency here.
            // Implementations may override by binding a different trait or enabling a queue job later.
        }
    }

    /**
     * Clear the conversation and reset state.
     */
    public function clearConversation(): self
    {
        if ($this->conversationId && $this->persistenceStore) {
            $this->persistenceStore->delete($this->conversationId);
        }
        $this->conversationId = null;
        $this->persistenceEnabled = false;
        $this->messages = [];
        return $this;
    }

    /**
     * Get persisted history (recent messages).
     */
    public function getPersistedHistory(int $limit = 50): array
    {
        if (!$this->persistenceEnabled || !$this->conversationId) {
            return [];
        }
        return $this->persistenceStore->getRecentMessages($this->conversationId, $limit);
    }
}
