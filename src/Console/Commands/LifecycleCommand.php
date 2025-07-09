<?php

/**
 * LifecycleCommand - Agent Lifecycle Management
 * 
 * Purpose: Manages agent lifecycle including health checks, cleanup, statistics,
 * and pool management. This command provides comprehensive monitoring and
 * maintenance capabilities for agent systems.
 * 
 * Lifecycle Concept: Agent lifecycle management ensures efficient resource
 * utilization, health monitoring, and automatic cleanup of expired or
 * malfunctioning agents to maintain system performance and reliability.
 * 
 * Features Tested:
 * - Agent health checks and monitoring
 * - Expired agent cleanup
 * - Resource usage statistics
 * - Pool management and statistics
 * - Detailed health reporting
 * - Multiple output formats (table, JSON, CSV)
 * - Performance metrics and monitoring
 * 
 * Usage:
 * - Health check: php artisan agents:lifecycle health
 * - Cleanup: php artisan agents:lifecycle cleanup
 * - Statistics: php artisan agents:lifecycle stats
 * - Pool info: php artisan agents:lifecycle pool
 * - With details: php artisan agents:lifecycle health --detailed
 * - JSON output: php artisan agents:lifecycle stats --format=json
 * - CSV output: php artisan agents:lifecycle health --format=csv
 * 
 * Test Scenarios:
 * 1. Agent health checks and monitoring
 * 2. Expired agent cleanup and maintenance
 * 3. Resource usage statistics and reporting
 * 4. Pool management and agent distribution
 * 5. Detailed health reporting and analysis
 * 6. Multiple output format testing
 * 
 * Lifecycle Operations:
 * - health: Perform health checks on all agents
 * - cleanup: Clean up expired agents
 * - stats: Show lifecycle statistics
 * - pool: Display pool information
 * 
 * Health Metrics:
 * - Total agents count
 * - Healthy vs unhealthy agents
 * - Memory usage statistics
 * - Conversation count averages
 * - Performance metrics
 * - Resource utilization
 * 
 * Output Formats:
 * - table: Tabular output (default)
 * - json: JSON format
 * - csv: CSV format
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Lifecycle\AgentLifecycleManager;
use Sapiensly\OpenaiAgents\Lifecycle\AgentPool;
use Sapiensly\OpenaiAgents\Lifecycle\HealthChecker;

class LifecycleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agents:lifecycle 
                            {action : The action to perform (health, cleanup, stats, pool)}
                            {--detailed : Show detailed information}
                            {--format=table : Output format (table, json, csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage agent lifecycle (health checks, cleanup, statistics)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $detailed = $this->option('detailed');
        $format = $this->option('format');

        return match($action) {
            'health' => $this->handleHealthCheck($detailed, $format),
            'cleanup' => $this->handleCleanup($detailed, $format),
            'stats' => $this->handleStats($detailed, $format),
            'pool' => $this->handlePool($detailed, $format),
            default => $this->handleUnknownAction($action),
        };
    }

    /**
     * Handle health check action.
     */
    private function handleHealthCheck(bool $detailed, string $format): int
    {
        $this->info('🏥 Performing agent health checks...');
        
        $lifecycleManager = app(AgentLifecycleManager::class);
        $healthChecker = app(HealthChecker::class);

        // Get all agents
        $agents = $lifecycleManager->getAllAgents();
        
        if (empty($agents)) {
            $this->warn('No agents found to check.');
            return 0;
        }

        $this->withProgressBar($agents, function ($agent) use ($healthChecker) {
            $healthChecker->checkHealth($agent);
        });

        $this->newLine();

        // Perform health checks
        $healthChecks = $lifecycleManager->performHealthChecks();
        $summary = $healthChecker->getHealthSummary($agents);

        // Display results
        $this->displayHealthResults($healthChecks, $summary, $detailed, $format);

        return 0;
    }

    /**
     * Handle cleanup action.
     */
    private function handleCleanup(bool $detailed, string $format): int
    {
        $this->info('🧹 Cleaning up expired agents...');
        
        $lifecycleManager = app(AgentLifecycleManager::class);
        $pool = app(AgentPool::class);

        // Clean up expired agents
        $cleanedUp = $lifecycleManager->cleanupExpiredAgents();
        $poolCleanedUp = $pool->cleanupExpiredAgents();

        $this->info("✅ Cleanup completed:");
        $this->line("   • Lifecycle manager: {$cleanedUp} agents cleaned up");
        $this->line("   • Pool: {$poolCleanedUp} agents cleaned up");

        if ($detailed) {
            $this->newLine();
            $this->info('📊 Post-cleanup statistics:');
            $this->displayStats($lifecycleManager->getLifecycleStats(), $format);
        }

        return 0;
    }

    /**
     * Handle stats action.
     */
    private function handleStats(bool $detailed, string $format): int
    {
        $this->info('📊 Agent lifecycle statistics...');
        
        $lifecycleManager = app(AgentLifecycleManager::class);
        $pool = app(AgentPool::class);
        $healthChecker = app(HealthChecker::class);

        $stats = $lifecycleManager->getLifecycleStats();
        $poolStats = $pool->getStats();
        $resourceUsage = $lifecycleManager->getResourceUsage();

        $this->displayStats($stats, $format);
        
        if ($detailed) {
            $this->newLine();
            $this->info('🏊 Pool statistics:');
            $this->displayPoolStats($poolStats, $format);
            
            $this->newLine();
            $this->info('💾 Resource usage:');
            $this->displayResourceUsage($resourceUsage, $format);
        }

        return 0;
    }

    /**
     * Handle pool action.
     */
    private function handlePool(bool $detailed, string $format): int
    {
        $this->info('🏊 Agent pool information...');
        
        $pool = app(AgentPool::class);
        $stats = $pool->getStats();
        $agents = $pool->getAllAgents();

        $this->displayPoolStats($stats, $format);
        
        if ($detailed && !empty($agents)) {
            $this->newLine();
            $this->info('📋 Pool agents:');
            $this->displayPoolAgents($agents, $format);
        }

        return 0;
    }

    /**
     * Handle unknown action.
     */
    private function handleUnknownAction(string $action): int
    {
        $this->error("Unknown action: {$action}");
        $this->line('Available actions: health, cleanup, stats, pool');
        return 1;
    }

    /**
     * Display health check results.
     */
    private function displayHealthResults(array $healthChecks, array $summary, bool $detailed, string $format): void
    {
        if ($format === 'json') {
            $this->output->write(json_encode([
                'summary' => $summary,
                'details' => $healthChecks,
            ], JSON_PRETTY_PRINT));
            return;
        }

        // Display summary
        $this->info('📋 Health Check Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Agents', $summary['total_agents']],
                ['Healthy Agents', $summary['healthy_agents']],
                ['Unhealthy Agents', $summary['unhealthy_agents']],
                ['Average Memory Usage', $this->formatBytes($summary['average_memory_usage'])],
                ['Average Conversation Count', $summary['average_conversation_count']],
            ]
        );

        if ($detailed && !empty($summary['issues'])) {
            $this->newLine();
            $this->warn('⚠️  Issues Found:');
            
            $issues = [];
            foreach ($summary['issues'] as $issue) {
                $issues[] = [
                    $issue['agent_id'],
                    $issue['check_type'],
                    $this->formatIssueDetails($issue['details']),
                ];
            }
            
            $this->table(
                ['Agent ID', 'Check Type', 'Details'],
                $issues
            );
        }
    }

    /**
     * Display statistics.
     */
    private function displayStats(array $stats, string $format): void
    {
        if ($format === 'json') {
            $this->output->write(json_encode($stats, JSON_PRETTY_PRINT));
            return;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Agents Created', $stats['total_agents_created']],
                ['Total Agents Destroyed', $stats['total_agents_destroyed']],
                ['Current Pool Size', $stats['current_pool_size']],
            ]
        );
    }

    /**
     * Display pool statistics.
     */
    private function displayPoolStats(array $stats, string $format): void
    {
        if ($format === 'json') {
            $this->output->write(json_encode($stats, JSON_PRETTY_PRINT));
            return;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Pool Size', $stats['pool_size']],
                ['Available Agents', $stats['available_agents']],
                ['Total Created', $stats['total_created']],
                ['Total Destroyed', $stats['total_destroyed']],
                ['Hit Rate', $stats['hit_rate'] . '%'],
                ['Hits', $stats['hits']],
                ['Misses', $stats['misses']],
            ]
        );
    }

    /**
     * Display resource usage.
     */
    private function displayResourceUsage(array $usage, string $format): void
    {
        if ($format === 'json') {
            $this->output->write(json_encode($usage, JSON_PRETTY_PRINT));
            return;
        }

        $this->table(
            ['Resource', 'Total', 'Average', 'Peak'],
            [
                ['Memory', $this->formatBytes($usage['memory']['total']), $this->formatBytes($usage['memory']['average']), $this->formatBytes($usage['memory']['peak'])],
                ['API Calls', $usage['api_calls']['total'], $usage['api_calls']['average'], '-'],
                ['Conversations', $usage['conversations']['total'], $usage['conversations']['average'], '-'],
            ]
        );
    }

    /**
     * Display pool agents.
     */
    private function displayPoolAgents(array $agents, string $format): void
    {
        if ($format === 'json') {
            $this->output->write(json_encode($agents, JSON_PRETTY_PRINT));
            return;
        }

        $agentData = [];
        foreach ($agents as $agent) {
            $agentData[] = [
                $agent->getId(),
                count($agent->getMessages()),
                $this->formatBytes(memory_get_usage(true)),
            ];
        }

        $this->table(
            ['Agent ID', 'Messages', 'Memory Usage'],
            $agentData
        );
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format issue details.
     */
    private function formatIssueDetails(array $details): string
    {
        $issues = [];
        
        if (isset($details['memory']) && !$details['memory']['healthy']) {
            $issues[] = 'Memory: ' . $details['memory']['percentage'] . '%';
        }
        
        if (isset($details['conversation']) && !$details['conversation']['healthy']) {
            $issues[] = 'Conversations: ' . $details['conversation']['count'] . '/' . $details['conversation']['limit'];
        }
        
        if (isset($details['responsiveness']) && !$details['responsiveness']['healthy']) {
            $issues[] = 'Unresponsive';
        }
        
        return implode(', ', $issues);
    }
} 