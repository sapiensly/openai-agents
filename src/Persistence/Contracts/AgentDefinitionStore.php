<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Contracts;

interface AgentDefinitionStore
{
    /**
     * Persist the agent definition payload for a given agent id.
     */
    public function save(string $agentId, array $definition): void;

    /**
     * Load a previously saved agent definition payload.
     *
     * Should return null when the definition is not found or persistence is disabled.
     */
    public function load(string $agentId): ?array;

    /**
     * Remove a persisted agent definition payload.
     */
    public function delete(string $agentId): void;
}
