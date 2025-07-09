<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Lifecycle;

use Sapiensly\OpenaiAgents\Agent;
use Illuminate\Support\Facades\Log;

/**
 * Class AgentPool
 *
 * Manages a pool of agents for efficient resource utilization and reuse.
 */
class AgentPool
{
    /**
     * The agent pool.
     */
    private array $pool = [];

    /**
     * Pool configuration.
     */
    private array $config;

    /**
     * Pool statistics.
     */
    private array $stats = [
        'total_created' => 0,
        'total_destroyed' => 0,
        'current_size' => 0,
        'hits' => 0,
        'misses' => 0,
    ];

    /**
     * Create a new AgentPool instance.
     *
     * @param array $config The pool configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_size' => 50,
            'min_size' => 5,
            'max_idle_time' => 1800, // 30 minutes
            'cleanup_interval' => 300, // 5 minutes
            'enable_stats' => true,
        ], $config);
    }

    /**
     * Add an agent to the pool.
     *
     * @param Agent $agent The agent to add
     * @param array $metadata Additional metadata
     * @return void
     */
    public function addAgent(Agent $agent, array $metadata = []): void
    {
        $agentId = $agent->getId();

        if (isset($this->pool[$agentId])) {
            Log::warning("[AgentPool] Agent {$agentId} already exists in pool");
            return;
        }

        if ($this->getPoolSize() >= $this->config['max_size']) {
            Log::warning("[AgentPool] Pool is full, cannot add agent {$agentId}");
            return;
        }

        $this->pool[$agentId] = [
            'agent' => $agent,
            'created_at' => time(),
            'last_used' => time(),
            'usage_count' => 0,
            'metadata' => $metadata,
            'available' => true,
        ];

        $this->stats['current_size'] = count($this->pool);
        $this->stats['total_created']++;

        Log::info("[AgentPool] Added agent {$agentId} to pool");
    }

    /**
     * Get an agent from the pool.
     *
     * @param array $criteria The search criteria
     * @return Agent|null The agent or null if not found
     */
    public function getAgent(array $criteria = []): ?Agent
    {
        $agent = $this->findAvailableAgent($criteria);

        if ($agent) {
            $this->markAgentAsUsed($agent);
            $this->stats['hits']++;
            return $agent;
        }

        $this->stats['misses']++;
        return null;
    }

    /**
     * Return an agent to the pool.
     *
     * @param Agent $agent The agent to return
     * @return void
     */
    public function returnAgent(Agent $agent): void
    {
        $agentId = $agent->getId();

        if (!isset($this->pool[$agentId])) {
            Log::warning("[AgentPool] Agent {$agentId} not found in pool");
            return;
        }

        $this->pool[$agentId]['available'] = true;
        $this->pool[$agentId]['last_used'] = time();

        Log::info("[AgentPool] Returned agent {$agentId} to pool");
    }

    /**
     * Remove an agent from the pool.
     *
     * @param Agent $agent The agent to remove
     * @return void
     */
    public function removeAgent(Agent $agent): void
    {
        $agentId = $agent->getId();

        if (!isset($this->pool[$agentId])) {
            Log::warning("[AgentPool] Agent {$agentId} not found in pool");
            return;
        }

        unset($this->pool[$agentId]);
        $this->stats['current_size'] = count($this->pool);
        $this->stats['total_destroyed']++;

        Log::info("[AgentPool] Removed agent {$agentId} from pool");
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
        $maxIdleTime = $this->config['max_idle_time'];

        foreach ($this->pool as $agentId => $agentData) {
            if ($currentTime - $agentData['last_used'] > $maxIdleTime) {
                $this->removeAgent($agentData['agent']);
                $cleanedUp++;
            }
        }

        Log::info("[AgentPool] Cleaned up {$cleanedUp} expired agents");

        return $cleanedUp;
    }

    /**
     * Get pool statistics.
     *
     * @return array Pool statistics
     */
    public function getStats(): array
    {
        $stats = $this->stats;
        $stats['hit_rate'] = $this->calculateHitRate();
        $stats['pool_size'] = $this->getPoolSize();
        $stats['available_agents'] = $this->getAvailableAgentCount();

        return $stats;
    }

    /**
     * Get all agents in the pool.
     *
     * @return array All agents
     */
    public function getAllAgents(): array
    {
        return array_map(fn($data) => $data['agent'], $this->pool);
    }

    /**
     * Get available agents.
     *
     * @return array Available agents
     */
    public function getAvailableAgents(): array
    {
        return array_map(
            fn($data) => $data['agent'],
            array_filter($this->pool, fn($data) => $data['available'])
        );
    }

    /**
     * Get pool size.
     *
     * @return int Pool size
     */
    public function getPoolSize(): int
    {
        return count($this->pool);
    }

    /**
     * Get available agent count.
     *
     * @return int Available agent count
     */
    public function getAvailableAgentCount(): int
    {
        return count(array_filter($this->pool, fn($data) => $data['available']));
    }

    /**
     * Check if pool is empty.
     *
     * @return bool True if empty
     */
    public function isEmpty(): bool
    {
        return empty($this->pool);
    }

    /**
     * Check if pool is full.
     *
     * @return bool True if full
     */
    public function isFull(): bool
    {
        return $this->getPoolSize() >= $this->config['max_size'];
    }

    /**
     * Find an available agent matching criteria.
     *
     * @param array $criteria The search criteria
     * @return Agent|null The agent or null
     */
    private function findAvailableAgent(array $criteria): ?Agent
    {
        foreach ($this->pool as $agentData) {
            if (!$agentData['available']) {
                continue;
            }

            $agent = $agentData['agent'];

            if ($this->agentMatchesCriteria($agent, $criteria)) {
                return $agent;
            }
        }

        return null;
    }

    /**
     * Check if agent matches criteria.
     *
     * @param Agent $agent The agent
     * @param array $criteria The criteria
     * @return bool True if matches
     */
    private function agentMatchesCriteria(Agent $agent, array $criteria): bool
    {
        if (empty($criteria)) {
            return true;
        }

        $agentOptions = $agent->getOptions();

        foreach ($criteria as $key => $value) {
            if (!isset($agentOptions[$key]) || $agentOptions[$key] !== $value) {
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

        if (isset($this->pool[$agentId])) {
            $this->pool[$agentId]['available'] = false;
            $this->pool[$agentId]['usage_count']++;
        }
    }

    /**
     * Calculate hit rate.
     *
     * @return float Hit rate
     */
    private function calculateHitRate(): float
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->stats['hits'] / $total) * 100, 2);
    }
} 