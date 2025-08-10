<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Security;

use Sapiensly\OpenaiAgents\Handoff\HandoffSecurityException;
use Sapiensly\OpenaiAgents\Registry\AgentRegistry;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentOptions;
use Illuminate\Support\Facades\Log;

/**
 * Class SecurityManager
 *
 * Manages security policies using simplified array-based permissions with Agent objects.
 */
class SecurityManager
{
    private AgentRegistry $registry;
    private array $config;

    public function __construct(AgentRegistry $registry, array $config = [])
    {
        $this->registry = $registry;
        $this->config = $config;
    }

    /**
     * Validate handoff permission using Agent objects.
     *
     * @param Agent $sourceAgent The agent requesting handoff
     * @param Agent $targetAgent The agent receiving handoff
     * @throws HandoffSecurityException
     */
    public function validateHandoffPermission(Agent $sourceAgent, Agent $targetAgent): void
    {
        $sourceId = $sourceAgent->getId() ?? 'unknown_source';
        $targetId = $targetAgent->getId() ?? 'unknown_target';

        Log::debug('[SecurityManager] Validating handoff permission (Agent objects)', [
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'source_capabilities' => $this->getAgentCapabilities($sourceAgent),
            'target_capabilities' => $this->getAgentCapabilities($targetAgent),
        ]);

        if (!$this->canReceiveFrom($targetAgent, $sourceAgent)) {
            throw new HandoffSecurityException(
                "Agent '{$targetId}' does not accept handoffs from '{$sourceId}'"
            );
        }
    }

    /**
     * Check if target agent can receive handoff from source agent.
     */
    private function canReceiveFrom(Agent $targetAgent, Agent $sourceAgent): bool
    {
        // 1. First check AgentOptions for target agent
        // This allows for dynamic configuration per agent
        // and is the preferred method for defining handoff permissions.
        Log::debug('[SecurityManager] Checking AgentOptions for target agent', [
            'target_id' => $targetAgent->getId(),
            'source_id' => $sourceAgent->getId()
        ]);
        $targetOptions = $this->getAgentOptions($targetAgent);
        // Log
        Log::debug('[SecurityManager] Checking AgentOptions for target agent', [
            'target_id' => $targetAgent->getId(),
            'handoff_target_permission' => $targetOptions->handoff_target_permission ?? null
        ]);
        if ($targetOptions && $targetOptions->handoff_target_permission !== null) {
            return $this->checkPermissionArray(
                $targetOptions->handoff_target_permission,
                $sourceAgent
            );
        }

        // 2. If no AgentOptions, check agent-specific configuration
        Log::debug('[SecurityManager] Checking agent-specific configuration', [
            'target_id' => $targetAgent->getId(),
            'source_id' => $sourceAgent->getId()
        ]);
        // This allows for static configuration per agent in the system config.
        // It is useful for defining permissions that are not dynamic.
        $targetId = $targetAgent->getId();
        $agentSpecificConfig = $this->config['security']['agent_specific'][$targetId] ?? null;
        if ($agentSpecificConfig) {
            return $this->checkPermissionArray($agentSpecificConfig, $sourceAgent);
        }

        // 3. Usar configuración por defecto del sistema
        Log::debug('[SecurityManager] Using default target permission configuration', [
            'target_id' => $targetAgent->getId(),
            'source_id' => $sourceAgent->getId()
        ]);
        // This is the fallback for when no specific configuration is found.
        // It allows for a default permission set that applies to all agents.
        // It is useful for defining a baseline permission that applies to all agents.
        $defaultPermission = $this->config['security']['default_target_permission'] ?? ['*'];
        return $this->checkPermissionArray($defaultPermission, $sourceAgent);
    }

    /**
     * Check permissions using the simplified array format with Agent object.
     *
     * Examples:
     * ['*'] = allow all
     * ['agent_id_1', 'agent_id_2'] = only these agents
     * ['*', 'blacklist' => ['bad_*']] = all except blacklisted
     * [] = deny all
     *
     * @param array $permission Permission configuration
     * @param Agent $sourceAgent Source agent object
     * @return bool
     */
    private function checkPermissionArray(array $permission, Agent $sourceAgent): bool
    {
        // Empty array = deny all
        if (empty($permission)) {
            Log::debug('[SecurityManager] Empty permission array, denying access');
            return false;
        }

        $sourceId = $sourceAgent->getId() ?? 'unknown';
        $sourceCapabilities = $this->getAgentCapabilities($sourceAgent);

        // Check if there's a blacklist
        if (isset($permission['blacklist'])) {
            $blacklist = $permission['blacklist'];

            // Check if source is blacklisted by ID or capabilities
            foreach ($blacklist as $pattern) {
                if ($this->agentMatchesPattern($sourceAgent, $pattern)) {
                    Log::debug('[SecurityManager] Source agent matches blacklist pattern', [
                        'source_id' => $sourceId,
                        'pattern' => $pattern,
                        'capabilities' => $sourceCapabilities
                    ]);
                    return false; // Blacklisted
                }
            }

            // Remove blacklist key to process whitelist normally
            $whitelistPermission = array_filter($permission, function($key) {
                return $key !== 'blacklist';
            }, ARRAY_FILTER_USE_KEY);
        } else {
            $whitelistPermission = $permission;
        }

        // Check whitelist
        if (in_array('*', $whitelistPermission)) {
            Log::debug('[SecurityManager] Wildcard permission found, allowing access');
            return true;
        }

        // Check specific permissions (ID, capabilities, patterns)
        foreach ($whitelistPermission as $pattern) {
            if ($this->agentMatchesPattern($sourceAgent, $pattern)) {
                Log::debug('[SecurityManager] Source agent matches whitelist pattern', [
                    'source_id' => $sourceId,
                    'pattern' => $pattern,
                    'capabilities' => $sourceCapabilities
                ]);
                return true;
            }
        }

        Log::debug('[SecurityManager] No matching permission found, denying access');
        return false;
    }

    /**
     * Check if an agent matches a given pattern.
     * Supports ID matching, capability matching, and wildcard patterns.
     */
    private function agentMatchesPattern(Agent $agent, string $pattern): bool
    {
        $agentId = $agent->getId() ?? 'unknown';
        $capabilities = $this->getAgentCapabilities($agent);

        // 1. Exact ID match
        if ($agentId === $pattern) {
            return true;
        }

        // 2. Capability match (using capability: prefix)
        if (str_starts_with($pattern, 'capability:')) {
            $requiredCapability = substr($pattern, 11); // Remove 'capability:' prefix
            return in_array($requiredCapability, $capabilities);
        }

        // 3. Wildcard pattern match on ID
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
            return preg_match($regex, $agentId) === 1;
        }

        return false;
    }

    /**
     * Get agent capabilities from various sources.
     */
    private function getAgentCapabilities(Agent $agent): array
    {
        $capabilities = [];

        // From AgentOptions
        $options = $this->getAgentOptions($agent);
        if ($options && $options->capabilities) {
            $capabilities = array_merge($capabilities, $options->capabilities);
        }

        // From registry
        $agentId = $agent->getId();
        if ($agentId) {
            $registryCapabilities = $this->registry->getAgentCapabilities($agentId);
            $capabilities = array_merge($capabilities, $registryCapabilities);
        }

        // From config
        $configCapabilities = $this->config['handoff']['capabilities'][$agentId] ?? [];
        $capabilities = array_merge($capabilities, $configCapabilities);

        return array_unique($capabilities);
    }

    /**
     * Get AgentOptions from an Agent safely.
     */
    private function getAgentOptions(Agent $agent): ?AgentOptions
    {
        try {
            $optionsArray = $agent->getOptions();
            return $optionsArray ? AgentOptions::fromArray($optionsArray) : null;
        } catch (\Exception $e) {
            Log::warning('[SecurityManager] Failed to get agent options', [
                'agent_id' => $agent->getId(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get the effective permission array for an agent.
     */
    public function getAgentPermissions(Agent $agent): array
    {
        // From AgentOptions first
        $options = $this->getAgentOptions($agent);
        if ($options && $options->handoff_target_permission !== null) {
            return $options->handoff_target_permission;
        }

        // From config specific
        $agentId = $agent->getId();
        $agentSpecific = $this->config['security']['agent_specific'][$agentId] ?? null;
        if ($agentSpecific) {
            return $agentSpecific;
        }

        // Default
        return $this->config['security']['default_target_permission'] ?? ['*'];
    }

    /**
     * Check if an agent has a specific capability.
     */
    public function agentHasCapability(Agent $agent, string $capability): bool
    {
        $capabilities = $this->getAgentCapabilities($agent);
        return in_array($capability, $capabilities);
    }

    /**
     * Find agents with specific capabilities.
     */
    public function findAgentsWithCapability(string $capability): array
    {
        $matchingAgents = [];

        foreach ($this->registry->getAllAgents() as $agentId => $agent) {
            if ($this->agentHasCapability($agent, $capability)) {
                $matchingAgents[$agentId] = $agent;
            }
        }

        return $matchingAgents;
    }

    /**
     * Validate if source agent has permission to handoff to any agent with specific capability.
     */
    public function canHandoffToCapability(Agent $sourceAgent, string $capability): bool
    {
        $agentsWithCapability = $this->findAgentsWithCapability($capability);

        foreach ($agentsWithCapability as $targetAgent) {
            try {
                $this->validateHandoffPermission($sourceAgent, $targetAgent);
                return true; // Found at least one valid target
            } catch (HandoffSecurityException $e) {
                // Continue checking other agents
                continue;
            }
        }

        return false; // No valid targets found
    }
}
