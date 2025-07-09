<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Lifecycle;

use Sapiensly\OpenaiAgents\Agent;
use Illuminate\Support\Facades\Log;

/**
 * Class HealthChecker
 *
 * Performs comprehensive health checks on agents including memory usage,
 * responsiveness, and resource consumption.
 */
class HealthChecker
{
    /**
     * Health check configuration.
     */
    private array $config;

    /**
     * Health check results cache.
     */
    private array $results = [];

    /**
     * Create a new HealthChecker instance.
     *
     * @param array $config The health check configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'memory_threshold' => 50 * 1024 * 1024, // 50MB
            'conversation_threshold' => 1000,
            'response_timeout' => 5, // seconds
            'enable_caching' => true,
            'cache_ttl' => 300, // 5 minutes
        ], $config);
    }

    /**
     * Perform a comprehensive health check on an agent.
     *
     * @param Agent $agent The agent to check
     * @return array Health check results
     */
    public function checkHealth(Agent $agent): array
    {
        $agentId = $agent->getId();
        $cacheKey = "health_check_{$agentId}";

        // Check cache first
        if ($this->config['enable_caching'] && isset($this->results[$cacheKey])) {
            $cached = $this->results[$cacheKey];
            if (time() - $cached['timestamp'] < $this->config['cache_ttl']) {
                return $cached['result'];
            }
        }

        $result = [
            'agent_id' => $agentId,
            'timestamp' => time(),
            'overall_healthy' => true,
            'checks' => [],
        ];

        // Memory usage check
        $memoryCheck = $this->checkMemoryUsage($agent);
        $result['checks']['memory'] = $memoryCheck;
        $result['overall_healthy'] = $result['overall_healthy'] && $memoryCheck['healthy'];

        // Conversation count check
        $conversationCheck = $this->checkConversationCount($agent);
        $result['checks']['conversation'] = $conversationCheck;
        $result['overall_healthy'] = $result['overall_healthy'] && $conversationCheck['healthy'];

        // Responsiveness check
        $responsivenessCheck = $this->checkResponsiveness($agent);
        $result['checks']['responsiveness'] = $responsivenessCheck;
        $result['overall_healthy'] = $result['overall_healthy'] && $responsivenessCheck['healthy'];

        // Resource usage check
        $resourceCheck = $this->checkResourceUsage($agent);
        $result['checks']['resources'] = $resourceCheck;
        $result['overall_healthy'] = $result['overall_healthy'] && $resourceCheck['healthy'];

        // Cache the result
        if ($this->config['enable_caching']) {
            $this->results[$cacheKey] = [
                'result' => $result,
                'timestamp' => time(),
            ];
        }

        return $result;
    }

    /**
     * Check memory usage.
     *
     * @param Agent $agent The agent
     * @return array Memory check result
     */
    private function checkMemoryUsage(Agent $agent): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->config['memory_threshold'];
        $healthy = $memoryUsage < $memoryLimit;

        return [
            'healthy' => $healthy,
            'usage' => $memoryUsage,
            'limit' => $memoryLimit,
            'percentage' => round(($memoryUsage / $memoryLimit) * 100, 2),
        ];
    }

    /**
     * Check conversation count.
     *
     * @param Agent $agent The agent
     * @return array Conversation check result
     */
    private function checkConversationCount(Agent $agent): array
    {
        $conversationCount = count($agent->getMessages());
        $conversationLimit = $this->config['conversation_threshold'];
        $healthy = $conversationCount < $conversationLimit;

        return [
            'healthy' => $healthy,
            'count' => $conversationCount,
            'limit' => $conversationLimit,
            'percentage' => round(($conversationCount / $conversationLimit) * 100, 2),
        ];
    }

    /**
     * Check agent responsiveness.
     *
     * @param Agent $agent The agent
     * @return array Responsiveness check result
     */
    private function checkResponsiveness(Agent $agent): array
    {
        $startTime = microtime(true);
        $responsive = false;
        $error = null;

        try {
            // Simple responsiveness test
            $client = $agent->getClient();
            $messages = $agent->getMessages();
            
            // Check if agent has basic functionality
            $responsive = $client !== null && is_array($messages);
            
            if (!$responsive) {
                $error = 'Agent lacks basic functionality';
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $responseTime = microtime(true) - $startTime;

        return [
            'healthy' => $responsive,
            'response_time' => round($responseTime * 1000, 2), // milliseconds
            'responsive' => $responsive,
            'error' => $error,
        ];
    }

    /**
     * Check resource usage.
     *
     * @param Agent $agent The agent
     * @return array Resource check result
     */
    private function checkResourceUsage(Agent $agent): array
    {
        $usage = [
            'cpu_time' => 0,
            'memory_peak' => memory_get_peak_usage(true),
            'file_descriptors' => 0,
        ];

        // Try to get CPU time if available
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $usage['cpu_load'] = $load[0] ?? 0;
        }

        // Check if usage is within acceptable limits
        $healthy = $usage['memory_peak'] < $this->config['memory_threshold'];

        return [
            'healthy' => $healthy,
            'usage' => $usage,
        ];
    }

    /**
     * Perform health check on multiple agents.
     *
     * @param array $agents The agents to check
     * @return array Health check results
     */
    public function checkMultipleAgents(array $agents): array
    {
        $results = [];

        foreach ($agents as $agent) {
            $results[$agent->getId()] = $this->checkHealth($agent);
        }

        return $results;
    }

    /**
     * Get health check summary.
     *
     * @param array $agents The agents to summarize
     * @return array Health check summary
     */
    public function getHealthSummary(array $agents): array
    {
        $results = $this->checkMultipleAgents($agents);
        
        $summary = [
            'total_agents' => count($agents),
            'healthy_agents' => 0,
            'unhealthy_agents' => 0,
            'average_memory_usage' => 0,
            'average_conversation_count' => 0,
            'issues' => [],
        ];

        $totalMemory = 0;
        $totalConversations = 0;

        foreach ($results as $agentId => $result) {
            if ($result['overall_healthy']) {
                $summary['healthy_agents']++;
            } else {
                $summary['unhealthy_agents']++;
                
                // Collect issues
                foreach ($result['checks'] as $checkType => $check) {
                    if (!$check['healthy']) {
                        $summary['issues'][] = [
                            'agent_id' => $agentId,
                            'check_type' => $checkType,
                            'details' => $check,
                        ];
                    }
                }
            }

            $totalMemory += $result['checks']['memory']['usage'];
            $totalConversations += $result['checks']['conversation']['count'];
        }

        if (count($agents) > 0) {
            $summary['average_memory_usage'] = round($totalMemory / count($agents));
            $summary['average_conversation_count'] = round($totalConversations / count($agents));
        }

        return $summary;
    }

    /**
     * Clear health check cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->results = [];
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cached_results' => count($this->results),
            'cache_enabled' => $this->config['enable_caching'],
            'cache_ttl' => $this->config['cache_ttl'],
        ];
    }
} 