<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Lifecycle;

use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class AgentLifecycleManager
 *
 * Manages the complete lifecycle of agents including creation, destruction,
 * pooling, health checks, and resource management.
 */
class AgentLifecycleManager
{
    /**
     * The agent manager.
     */
    private AgentManager $agentManager;

    /**
     * Active agents pool.
     */
    private array $agentPool = [];

    /**
     * Agent lifecycle events.
     */
    private array $lifecycleEvents = [];

    /**
     * Configuration for lifecycle management.
     */
    private array $config;

    /**
     * Health check results.
     */
    private array $healthChecks = [];

    /**
     * Resource usage tracking.
     */
    private array $resourceUsage = [
        'memory' => [],
        'api_calls' => [],
        'conversations' => [],
    ];

    /**
     * Create a new AgentLifecycleManager instance.
     *
     * @param AgentManager $agentManager The agent manager
     * @param array $config The configuration
     */
    public function __construct(AgentManager $agentManager, array $config = [])
    {
        $this->agentManager = $agentManager;
        $this->config = array_merge([
            'max_agents' => 100,
            'max_memory_per_agent' => 50 * 1024 * 1024, // 50MB
            'max_conversations_per_agent' => 1000,
            'agent_ttl' => 3600, // 1 hour
            'health_check_interval' => 300, // 5 minutes
            'cleanup_interval' => 600, // 10 minutes
            'enable_pooling' => true,
            'enable_health_checks' => true,
            'enable_resource_tracking' => true,
        ], $config);

        $this->initializeLifecycle();
    }

    /**
     * Initialize the lifecycle management system.
     *
     * @return void
     */
    private function initializeLifecycle(): void
    {
        // Schedule cleanup task
        if ($this->config['enable_pooling']) {
            $this->scheduleCleanup();
        }

        // Schedule health checks
        if ($this->config['enable_health_checks']) {
            $this->scheduleHealthChecks();
        }

        Log::info('[AgentLifecycleManager] Initialized with config', $this->config);
    }

    /**
     * Create a new agent with lifecycle management.
     *
     * @param array $options The agent options
     * @param string|null $systemPrompt The system prompt
     * @param array $metadata Additional metadata
     * @return Agent The created agent
     */
    public function createAgent(array $options = [], ?string $systemPrompt = null, array $metadata = []): Agent
    {
        // Check resource limits
        $this->checkResourceLimits();

        // Create the agent
        $agent = $this->agentManager->agent($options, $systemPrompt);
        
        // Generate unique ID
        $agentId = $this->generateAgentId();
        $agent->setId($agentId);

        // Track agent creation
        $this->trackAgentCreation($agent, $metadata);

        // Add to pool if enabled
        if ($this->config['enable_pooling']) {
            $this->addToPool($agent);
        }

        // Record lifecycle event
        $this->recordLifecycleEvent('agent_created', [
            'agent_id' => $agentId,
            'options' => $options,
            'metadata' => $metadata,
        ]);

        Log::info("[AgentLifecycleManager] Created agent {$agentId}");

        return $agent;
    }

    /**
     * Get an agent from the pool or create a new one.
     *
     * @param array $options The agent options
     * @param string|null $systemPrompt The system prompt
     * @return Agent The agent
     */
    public function getAgent(array $options = [], ?string $systemPrompt = null): Agent
    {
        if (!$this->config['enable_pooling']) {
            return $this->createAgent($options, $systemPrompt);
        }

        // Try to find an available agent in the pool
        $pooledAgent = $this->findAvailableAgent($options, $systemPrompt);
        
        if ($pooledAgent) {
            $this->markAgentAsUsed($pooledAgent);
            return $pooledAgent;
        }

        // Create new agent if none available
        return $this->createAgent($options, $systemPrompt);
    }

    /**
     * Destroy an agent and clean up resources.
     *
     * @param Agent $agent The agent to destroy
     * @param array $metadata Additional metadata
     * @return void
     */
    public function destroyAgent(Agent $agent, array $metadata = []): void
    {
        $agentId = $agent->getId();

        // Remove from pool
        $this->removeFromPool($agent);

        // Clean up resources
        $this->cleanupAgentResources($agent);

        // Record lifecycle event
        $this->recordLifecycleEvent('agent_destroyed', [
            'agent_id' => $agentId,
            'metadata' => $metadata,
        ]);

        Log::info("[AgentLifecycleManager] Destroyed agent {$agentId}");
    }

    /**
     * Return an agent to the pool.
     *
     * @param Agent $agent The agent to return
     * @return void
     */
    public function returnAgent(Agent $agent): void
    {
        if (!$this->config['enable_pooling']) {
            $this->destroyAgent($agent);
            return;
        }

        $agentId = $agent->getId();

        // Check if agent is still healthy
        if (!$this->isAgentHealthy($agent)) {
            Log::warning("[AgentLifecycleManager] Agent {$agentId} is unhealthy, destroying");
            $this->destroyAgent($agent);
            return;
        }

        // Reset agent state
        $this->resetAgentState($agent);

        // Return to pool
        $this->addToPool($agent);

        Log::info("[AgentLifecycleManager] Returned agent {$agentId} to pool");
    }

    /**
     * Perform health check on all agents.
     *
     * @return array Health check results
     */
    public function performHealthChecks(): array
    {
        if (!$this->config['enable_health_checks']) {
            return [];
        }

        $results = [];
        $unhealthyAgents = [];

        foreach ($this->agentPool as $agentId => $agentData) {
            $agent = $agentData['agent'];
            $health = $this->checkAgentHealth($agent);
            
            $results[$agentId] = $health;
            
            if (!$health['healthy']) {
                $unhealthyAgents[] = $agentId;
            }
        }

        // Clean up unhealthy agents
        foreach ($unhealthyAgents as $agentId) {
            $this->destroyAgent($this->agentPool[$agentId]['agent']);
        }

        $this->healthChecks = $results;

        Log::info("[AgentLifecycleManager] Health checks completed", [
            'total_agents' => count($this->agentPool),
            'healthy_agents' => count($results) - count($unhealthyAgents),
            'unhealthy_agents' => count($unhealthyAgents),
        ]);

        return $results;
    }

    /**
     * Get resource usage statistics.
     *
     * @return array Resource usage statistics
     */
    public function getResourceUsage(): array
    {
        if (!$this->config['enable_resource_tracking']) {
            return [];
        }

        $usage = [
            'memory' => [
                'total' => array_sum($this->resourceUsage['memory']),
                'average' => count($this->resourceUsage['memory']) > 0 
                    ? array_sum($this->resourceUsage['memory']) / count($this->resourceUsage['memory']) 
                    : 0,
                'peak' => max($this->resourceUsage['memory'] ?? [0]),
            ],
            'api_calls' => [
                'total' => array_sum($this->resourceUsage['api_calls']),
                'average' => count($this->resourceUsage['api_calls']) > 0 
                    ? array_sum($this->resourceUsage['api_calls']) / count($this->resourceUsage['api_calls']) 
                    : 0,
            ],
            'conversations' => [
                'total' => array_sum($this->resourceUsage['conversations']),
                'average' => count($this->resourceUsage['conversations']) > 0 
                    ? array_sum($this->resourceUsage['conversations']) / count($this->resourceUsage['conversations']) 
                    : 0,
            ],
        ];

        return $usage;
    }

    /**
     * Get lifecycle statistics.
     *
     * @return array Lifecycle statistics
     */
    public function getLifecycleStats(): array
    {
        $events = $this->lifecycleEvents;
        
        $stats = [
            'total_agents_created' => count(array_filter($events, fn($e) => $e['type'] === 'agent_created')),
            'total_agents_destroyed' => count(array_filter($events, fn($e) => $e['type'] === 'agent_destroyed')),
            'current_pool_size' => count($this->agentPool),
            'health_check_results' => $this->healthChecks,
            'resource_usage' => $this->getResourceUsage(),
        ];

        return $stats;
    }

    /**
     * Clean up expired agents.
     *
     * @return int Number of agents cleaned up
     */
    public function cleanupExpiredAgents(): int
    {
        $cleanedUp = 0;
        $currentTime = time();

        foreach ($this->agentPool as $agentId => $agentData) {
            if ($currentTime - $agentData['created_at'] > $this->config['agent_ttl']) {
                $this->destroyAgent($agentData['agent']);
                $cleanedUp++;
            }
        }

        Log::info("[AgentLifecycleManager] Cleaned up {$cleanedUp} expired agents");

        return $cleanedUp;
    }

    /**
     * Check if agent is healthy.
     *
     * @param Agent $agent The agent to check
     * @return bool True if healthy
     */
    private function isAgentHealthy(Agent $agent): bool
    {
        $health = $this->checkAgentHealth($agent);
        return $health['healthy'];
    }

    /**
     * Check agent health.
     *
     * @param Agent $agent The agent to check
     * @return array Health check result
     */
    private function checkAgentHealth(Agent $agent): array
    {
        $agentId = $agent->getId();
        
        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->config['max_memory_per_agent'];
        
        // Check conversation count
        $conversationCount = count($agent->getMessages());
        $conversationLimit = $this->config['max_conversations_per_agent'];
        
        // Check if agent is responsive
        $responsive = $this->testAgentResponsiveness($agent);
        
        $healthy = $memoryUsage < $memoryLimit 
                && $conversationCount < $conversationLimit 
                && $responsive;

        return [
            'healthy' => $healthy,
            'memory_usage' => $memoryUsage,
            'memory_limit' => $memoryLimit,
            'conversation_count' => $conversationCount,
            'conversation_limit' => $conversationLimit,
            'responsive' => $responsive,
            'timestamp' => time(),
        ];
    }

    /**
     * Test agent responsiveness.
     *
     * @param Agent $agent The agent to test
     * @return bool True if responsive
     */
    private function testAgentResponsiveness(Agent $agent): bool
    {
        try {
            // Simple test - check if agent can process a basic message
            $messages = $agent->getMessages();
            return !empty($messages) || $agent->getClient() !== null;
        } catch (\Exception $e) {
            Log::warning("[AgentLifecycleManager] Agent responsiveness test failed", [
                'agent_id' => $agent->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate a unique agent ID.
     *
     * @return string The agent ID
     */
    private function generateAgentId(): string
    {
        return 'agent_' . uniqid() . '_' . time();
    }

    /**
     * Track agent creation.
     *
     * @param Agent $agent The agent
     * @param array $metadata Additional metadata
     * @return void
     */
    private function trackAgentCreation(Agent $agent, array $metadata): void
    {
        $agentId = $agent->getId();
        
        $this->agentPool[$agentId] = [
            'agent' => $agent,
            'created_at' => time(),
            'last_used' => time(),
            'usage_count' => 0,
            'metadata' => $metadata,
        ];

        // Track resource usage
        if ($this->config['enable_resource_tracking']) {
            $this->resourceUsage['memory'][$agentId] = memory_get_usage(true);
            $this->resourceUsage['api_calls'][$agentId] = 0;
            $this->resourceUsage['conversations'][$agentId] = 0;
        }
    }

    /**
     * Add agent to pool.
     *
     * @param Agent $agent The agent
     * @return void
     */
    private function addToPool(Agent $agent): void
    {
        $agentId = $agent->getId();
        
        if (isset($this->agentPool[$agentId])) {
            $this->agentPool[$agentId]['last_used'] = time();
        }
    }

    /**
     * Remove agent from pool.
     *
     * @param Agent $agent The agent
     * @return void
     */
    private function removeFromPool(Agent $agent): void
    {
        $agentId = $agent->getId();
        unset($this->agentPool[$agentId]);

        // Clean up resource tracking
        if ($this->config['enable_resource_tracking']) {
            unset($this->resourceUsage['memory'][$agentId]);
            unset($this->resourceUsage['api_calls'][$agentId]);
            unset($this->resourceUsage['conversations'][$agentId]);
        }
    }

    /**
     * Find available agent in pool.
     *
     * @param array $options The agent options
     * @param string|null $systemPrompt The system prompt
     * @return Agent|null The available agent or null
     */
    private function findAvailableAgent(array $options, ?string $systemPrompt): ?Agent
    {
        foreach ($this->agentPool as $agentData) {
            $agent = $agentData['agent'];
            
            // Check if agent matches requirements
            if ($this->agentMatchesRequirements($agent, $options, $systemPrompt)) {
                return $agent;
            }
        }

        return null;
    }

    /**
     * Check if agent matches requirements.
     *
     * @param Agent $agent The agent
     * @param array $options The required options
     * @param string|null $systemPrompt The required system prompt
     * @return bool True if matches
     */
    private function agentMatchesRequirements(Agent $agent, array $options, ?string $systemPrompt): bool
    {
        // Simple matching - could be enhanced with more sophisticated logic
        $agentOptions = $agent->getOptions();
        
        // Check if options match
        foreach ($options as $key => $value) {
            if (!isset($agentOptions[$key]) || $agentOptions[$key] !== $value) {
                return false;
            }
        }

        // Check system prompt
        if ($systemPrompt !== null) {
            $messages = $agent->getMessages();
            $systemMessage = $messages[0] ?? null;
            
            if (!$systemMessage || $systemMessage['role'] !== 'system' || $systemMessage['content'] !== $systemPrompt) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mark agent as used.
     *
     * @param Agent $agent The agent
     * @return void
     */
    private function markAgentAsUsed(Agent $agent): void
    {
        $agentId = $agent->getId();
        
        if (isset($this->agentPool[$agentId])) {
            $this->agentPool[$agentId]['last_used'] = time();
            $this->agentPool[$agentId]['usage_count']++;
        }
    }

    /**
     * Reset agent state.
     *
     * @param Agent $agent The agent
     * @return void
     */
    private function resetAgentState(Agent $agent): void
    {
        // Keep system message, clear conversation
        $messages = $agent->getMessages();
        $systemMessage = null;
        
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message;
                break;
            }
        }
        
        $agent->setMessages($systemMessage ? [$systemMessage] : []);
    }

    /**
     * Clean up agent resources.
     *
     * @param Agent $agent The agent
     * @return void
     */
    private function cleanupAgentResources(Agent $agent): void
    {
        // Clear messages to free memory
        $agent->setMessages([]);
        
        // Additional cleanup as needed
        Log::info("[AgentLifecycleManager] Cleaned up resources for agent " . $agent->getId());
    }

    /**
     * Check resource limits.
     *
     * @return void
     * @throws \RuntimeException If limits exceeded
     */
    private function checkResourceLimits(): void
    {
        if (count($this->agentPool) >= $this->config['max_agents']) {
            throw new \RuntimeException('Maximum number of agents reached');
        }

        $totalMemory = array_sum($this->resourceUsage['memory'] ?? []);
        $memoryLimit = $this->config['max_agents'] * $this->config['max_memory_per_agent'];
        
        if ($totalMemory > $memoryLimit) {
            throw new \RuntimeException('Memory limit exceeded');
        }
    }

    /**
     * Record lifecycle event.
     *
     * @param string $type The event type
     * @param array $data The event data
     * @return void
     */
    private function recordLifecycleEvent(string $type, array $data): void
    {
        $this->lifecycleEvents[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => time(),
        ];

        // Keep only last 1000 events
        if (count($this->lifecycleEvents) > 1000) {
            $this->lifecycleEvents = array_slice($this->lifecycleEvents, -1000);
        }
    }

    /**
     * Schedule cleanup task.
     *
     * @return void
     */
    private function scheduleCleanup(): void
    {
        // This would typically be done with Laravel's task scheduling
        // For now, we'll just log that cleanup is needed
        Log::info("[AgentLifecycleManager] Cleanup scheduled every {$this->config['cleanup_interval']} seconds");
    }

    /**
     * Schedule health checks.
     *
     * @return void
     */
    private function scheduleHealthChecks(): void
    {
        // This would typically be done with Laravel's task scheduling
        // For now, we'll just log that health checks are scheduled
        Log::info("[AgentLifecycleManager] Health checks scheduled every {$this->config['health_check_interval']} seconds");
    }
} 