<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sapiensly\OpenaiAgents\Persistence\Contracts\ConversationStore;
use Sapiensly\OpenaiAgents\Persistence\Strategies\ContextStrategy;

trait PersistentAgentTrait
{
    protected ?string $conversationId = null;
    protected ?ConversationStore $persistenceStore = null;
    protected ?ContextStrategy $contextStrategy = null;
    protected bool $persistenceEnabled = false;
    protected bool $autoSummarize = true;
    protected int $summarizeAfter = 20;
    protected bool $historyHydrated = false;

    public function withConversation(?string $conversationId = null): self
    {
        // CRUCIAL: Reset state cuando se llama withConversation(),
        // especialmente importante para agentes deserializados con load()
        $this->resetPersistenceState();

        $this->conversationId = $conversationId ?: (string) Str::uuid();
        $this->persistenceEnabled = (bool) (config('sapiensly-openai-agents.persistence.enabled', true));

        // SIEMPRE re-resolver stores frescos desde container
        $this->persistenceStore = app(ConversationStore::class);
        $this->contextStrategy = app(ContextStrategy::class);

        // Create conversation
        $this->persistenceStore->findOrCreate($this->conversationId, [
            'agent_id' => method_exists($this, 'getId') ? $this->getId() : null,
            'model' => $this->options->get('model') ?? null,
        ]);

        // Rehidrata inmediatamente para agentes cargados
        $this->hydratePersistedHistoryIfNeeded();

        return $this;
    }

    /**
     * Reset persistence state - crítico para agentes deserializados
     */
    protected function resetPersistenceState(): void
    {
        $this->historyHydrated = false;
        $this->persistenceStore = null;
        $this->contextStrategy = null;
        // NO resetear conversationId ni persistenceEnabled aquí
        // ya que withConversation() los va a configurar inmediatamente
    }

    public function getConversationId(): ?string
    {
        return $this->conversationId;
    }

    protected function hydratePersistedHistoryIfNeeded(): void
    {
        Log::info('=== HYDRATION DEBUG START ===', [
            'historyHydrated' => $this->historyHydrated,
            'persistenceEnabled' => $this->persistenceEnabled,
            'conversationId' => $this->conversationId,
            'current_messages_count' => count($this->messages ?? [])
        ]);

        if ($this->historyHydrated || !$this->persistenceEnabled || !$this->conversationId) {
            Log::info('Skipping hydration', [
                'reason' => $this->historyHydrated ? 'already_hydrated' :
                    (!$this->persistenceEnabled ? 'persistence_disabled' : 'no_conversation_id')
            ]);
            return;
        }

        $limit = $this->options->max_turns ?? 50;
        Log::info('Fetching persisted messages', ['limit' => $limit]);

        $persisted = $this->persistenceStore->getRecentMessages($this->conversationId, $limit);

        Log::info('Persisted messages fetched', [
            'count' => count($persisted),
            'messages' => $persisted
        ]);

        if (empty($persisted)) {
            Log::info('No persisted messages found, marking as hydrated');
            $this->historyHydrated = true;
            return;
        }

        // Log current state
        $inMemory = $this->messages ?? [];
        Log::info('Current in-memory messages', [
            'count' => count($inMemory),
            'messages' => $inMemory
        ]);

        $preservedMsgs = array_values(array_filter($inMemory, static fn($m) =>
        in_array($m['role'] ?? null, ['system', 'developer'], true)
        ));

        Log::info('Preserved messages (system/developer)', [
            'count' => count($preservedMsgs),
            'messages' => $preservedMsgs
        ]);

        // Reconstruye: instrucciones preservadas + historial persistido
        $this->messages = $preservedMsgs;

        // Ordena persisted por fecha y agrega
        usort($persisted, function($a, $b) {
            $aTime = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
            $bTime = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $aTime <=> $bTime;
        });

        Log::info('Adding persisted messages to conversation');
        foreach ($persisted as $m) {
            $this->messages[] = [
                'role' => $m['role'] ?? 'user',
                'content' => $m['content'] ?? '',
            ];
        }

        Log::info('Hydration completed', [
            'final_message_count' => count($this->messages),
            'final_messages' => $this->messages
        ]);

        $this->historyHydrated = true;
        Log::info('=== HYDRATION DEBUG END ===');
    }

    /**
     * @throws \Exception
     */
    protected function persistMessage(string $role, string $content, array $metadata = []): void
    {
        Log::info('=== PERSIST MESSAGE DEBUG ===', [
            'role' => $role,
            'content' => substr($content, 0, 100) . '...',
            'persistenceEnabled' => $this->persistenceEnabled,
            'conversationId' => $this->conversationId,
            'hasStore' => $this->persistenceStore !== null
        ]);

        if (!$this->persistenceEnabled || !$this->conversationId) {
            Log::warning('Skipping persist message', [
                'reason' => !$this->persistenceEnabled ? 'persistence_disabled' : 'no_conversation_id'
            ]);
            return;
        }

        try {
            $this->persistenceStore->addMessage($this->conversationId, [
                'role' => $role,
                'content' => $content,
                'token_count' => $metadata['token_count'] ?? null,
                'metadata' => $metadata,
            ]);

            Log::info('Message persisted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to persist message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        $this->maybeSummarize();
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

    public function getPersistedHistory(int $limit = 50): array
    {
        if (!$this->persistenceEnabled || !$this->conversationId) {
            return [];
        }
        return $this->persistenceStore->getRecentMessages($this->conversationId, $limit);
    }

    /**
     * Minimal summarization trigger (deferred via afterResponse when available).
     */
    protected function maybeSummarize(): void
    {
        if (!$this->autoSummarize || !$this->persistenceEnabled || !$this->conversationId) {
            return;
        }
        $after = (int) (config('sapiensly-openai-agents.persistence.summarization.after_messages') ?? $this->summarizeAfter);
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
     * Check if persistence is enabled.
     */
    public function isPersistenceEnabled(): bool
    {
        return $this->persistenceEnabled && $this->conversationId && $this->persistenceStore;
    }

}


