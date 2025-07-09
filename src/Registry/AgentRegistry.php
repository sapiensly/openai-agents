<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Registry;

use Sapiensly\OpenaiAgents\Agent;

/**
 * Class AgentRegistry
 *
 * Registry for managing agents and their capabilities.
 * Provides methods for registering agents, finding agents by ID or capabilities,
 * and retrieving agent capabilities.
 */
class AgentRegistry
{
    /**
     * Array of registered agents, indexed by agent ID.
     *
     * @var array<string, Agent>
     */
    private array $agents = [];

    /**
     * Index of agent IDs by capability.
     * Used for quickly finding agents with specific capabilities.
     *
     * @var array<string, array<string>>
     */
    private array $capabilityIndex = [];

    /**
     * Register an agent with the registry.
     *
     * @param string $agentId The ID to associate with the agent
     * @param Agent $agent The agent instance to register
     * @param array $capabilities Array of capabilities the agent has
     * @return void
     */
    public function registerAgent(string $agentId, Agent $agent, array $capabilities = []): void
    {
        $this->agents[$agentId] = $agent;

        // Index by capabilities for quick lookup
        foreach ($capabilities as $capability) {
            if (!isset($this->capabilityIndex[$capability])) {
                $this->capabilityIndex[$capability] = [];
            }
            $this->capabilityIndex[$capability][] = $agentId;
        }
    }

    /**
     * Get an agent by ID.
     *
     * @param string $agentId The ID of the agent to retrieve
     * @return Agent|null The agent instance, or null if not found
     */
    public function getAgent(string $agentId): ?Agent
    {
        return $this->agents[$agentId] ?? null;
    }

    /**
     * Find agents that have all the specified capabilities.
     *
     * @param array $capabilities Array of capabilities to search for
     * @return array<Agent> Array of agent instances that have all the specified capabilities
     */
    public function findAgentsByCapabilities(array $capabilities): array
    {
        if (empty($capabilities)) {
            return [];
        }

        $matchingAgentIds = [];

        foreach ($capabilities as $capability) {
            if (isset($this->capabilityIndex[$capability])) {
                if (empty($matchingAgentIds)) {
                    $matchingAgentIds = $this->capabilityIndex[$capability];
                } else {
                    $matchingAgentIds = array_intersect($matchingAgentIds, $this->capabilityIndex[$capability]);
                }
            } else {
                // If any capability is not found, no agents match all capabilities
                return [];
            }
        }

        // Convert agent IDs to agent instances
        return array_map(
            fn($id) => $this->agents[$id],
            $matchingAgentIds
        );
    }

    /**
     * Get all registered agents.
     *
     * @return array<string, Agent> Array of all registered agents
     */
    public function getAllAgents(): array
    {
        return $this->agents;
    }

    /**
     * Get the capabilities of a specific agent.
     *
     * @param string $agentId The ID of the agent
     * @return array Array of capabilities the agent has
     */
    public function getAgentCapabilities(string $agentId): array
    {
        $capabilities = [];
        foreach ($this->capabilityIndex as $capability => $agentIds) {
            if (in_array($agentId, $agentIds)) {
                $capabilities[] = $capability;
            }
        }
        return $capabilities;
    }

    /**
     * Check if an agent has a specific capability.
     *
     * @param string $agentId The ID of the agent
     * @param string $capability The capability to check for
     * @return bool True if the agent has the capability, false otherwise
     */
    public function hasCapability(string $agentId, string $capability): bool
    {
        return isset($this->capabilityIndex[$capability]) &&
               in_array($agentId, $this->capabilityIndex[$capability]);
    }

    /**
     * Find the best agent for a set of capabilities based on a priority function.
     *
     * @param array $capabilities Array of required capabilities
     * @param callable|null $priorityFn Optional function to determine agent priority
     * @return Agent|null The best matching agent, or null if none found
     */
    public function findBestAgent(array $capabilities, ?callable $priorityFn = null): ?Agent
    {
        $candidates = $this->findAgentsByCapabilities($capabilities);

        if (empty($candidates)) {
            return null;
        }

        if ($priorityFn !== null) {
            // Sort candidates by priority
            usort($candidates, $priorityFn);
            return $candidates[0] ?? null;
        }

        // Default: return the first matching agent
        return reset($candidates);
    }
}
