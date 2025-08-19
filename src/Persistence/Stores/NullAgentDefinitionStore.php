<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Stores;

use Sapiensly\OpenaiAgents\Persistence\Contracts\AgentDefinitionStore;

/**
 * No-op store for agent definitions. Safe default to preserve existing behavior.
 */
class NullAgentDefinitionStore implements AgentDefinitionStore
{
    public function save(string $agentId, array $definition): void
    {
        // no-op
    }

    public function load(string $agentId): ?array
    {
        return null; // signifies not found / disabled
    }

    public function delete(string $agentId): void
    {
        // no-op
    }
}
