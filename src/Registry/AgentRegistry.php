<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Registry;

use Sapiensly\OpenaiAgents\Agent;

/**
 * Class AgentRegistry
 *
 * Manages agent registration and lookup by both ID and name.
 */
class AgentRegistry
{
    /**
     * Registered agents by ID.
     *
     * @var array<string, Agent>
     */
    private array $agentsById = [];

    /**
     * Registered agents by name.
     *
     * @var array<string, Agent>
     */
    private array $agentsByName = [];

    /**
     * Agent capabilities mapping.
     *
     * @var array<string, array>
     */
    private array $capabilities = [];

    /**
     * Register an agent with both ID and name.
     *
     * @param Agent $agent The agent to register
     * @param string|null $name Optional name for the agent
     * @param array $capabilities Agent capabilities
     * @return void
     */
    public function registerAgent(Agent $agent, ?string $name = null, array $capabilities = []): void
    {
        $agentId = $agent->getId();

        // Register by ID
        if ($agentId) {
            $this->agentsById[$agentId] = $agent;
            $this->capabilities[$agentId] = $capabilities;
        }

        // Register by name if provided
        if ($name !== null) {
            $this->agentsByName[$name] = $agent;
            $this->capabilities[$name] = $capabilities;
        }
    }

    /**
     * Get an agent by ID or name.
     *
     * @param string $identifier Agent ID or name
     * @return Agent|null The agent instance or null if not found
     */
    public function getAgent(string $identifier): ?Agent
    {
        // Try by ID first
        if (isset($this->agentsById[$identifier])) {
            return $this->agentsById[$identifier];
        }

        // Try by name
        if (isset($this->agentsByName[$identifier])) {
            return $this->agentsByName[$identifier];
        }

        return null;
    }

    /**
     * Get agent capabilities by ID or name.
     *
     * @param string $identifier Agent ID or name
     * @return array Agent capabilities
     */
    public function getAgentCapabilities(string $identifier): array
    {
        return $this->capabilities[$identifier] ?? [];
    }

    /**
     * Check if an agent exists by ID or name.
     *
     * @param string $identifier Agent ID or name
     * @return bool True if agent exists
     */
    public function hasAgent(string $identifier): bool
    {
        return isset($this->agentsById[$identifier]) || isset($this->agentsByName[$identifier]);
    }

    /**
     * Get all registered agents.
     *
     * @return array<string, Agent> All agents indexed by ID
     */
    public function getAllAgents(): array
    {
        return $this->agentsById;
    }

    /**
     * Get all registered agent names.
     *
     * @return array<string, Agent> All agents indexed by name
     */
    public function getAgentsByName(): array
    {
        return $this->agentsByName;
    }

    /**
     * Unregister an agent by ID or name.
     *
     * @param string $identifier Agent ID or name
     * @return bool True if agent was unregistered
     */
    public function unregisterAgent(string $identifier): bool
    {
        $found = false;

        // Remove by ID
        if (isset($this->agentsById[$identifier])) {
            unset($this->agentsById[$identifier]);
            unset($this->capabilities[$identifier]);
            $found = true;
        }

        // Remove by name
        if (isset($this->agentsByName[$identifier])) {
            unset($this->agentsByName[$identifier]);
            unset($this->capabilities[$identifier]);
            $found = true;
        }

        return $found;
    }
}
