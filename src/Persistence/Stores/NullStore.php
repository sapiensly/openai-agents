<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Stores;

use Sapiensly\OpenaiAgents\Persistence\Contracts\ConversationStore;

/**
 * NullStore is a no-op persistence implementation that keeps current behavior intact.
 */
class NullStore implements ConversationStore
{
    public function findOrCreate(string $id, ?array $metadata = []): array
    {
        return [
            'id' => $id,
            'metadata' => $metadata ?? [],
        ];
    }

    public function addMessage(string $conversationId, array $message): void
    {
        // no-op
    }

    public function getRecentMessages(string $conversationId, int $limit = 20): array
    {
        return [];
    }

    public function getSummary(string $conversationId): ?string
    {
        return null;
    }

    public function updateSummary(string $conversationId, string $summary): void
    {
        // no-op
    }

    public function delete(string $conversationId): void
    {
        // no-op
    }
}
