# Agent Lifecycle Management

## Overview

The Agent Lifecycle Management system provides comprehensive management of agent creation, destruction, pooling, health checks, and resource management. This system ensures efficient resource utilization, automatic cleanup, and monitoring of agent health.

## ✅ Implementation Summary

### 🏗️ **Core Components**

1. **AgentLifecycleManager** - Main orchestrator for agent lifecycle
   - Agent creation with resource limits
   - Automatic cleanup of expired agents
   - Resource tracking and management
   - Lifecycle event recording

2. **AgentPool** - Efficient agent reuse system
   - Configurable pool sizes
   - Hit/miss statistics
   - Automatic pool management
   - Agent matching based on criteria

3. **HealthChecker** - Comprehensive health monitoring
   - Memory usage monitoring
   - Conversation count tracking
   - Responsiveness testing
   - Resource consumption analysis

4. **LifecycleEvent** - Event tracking system
   - Lifecycle event interface
   - Event data structure
   - Timestamp tracking

### 🔧 **Integration**

- **Service Provider Registration** - All components registered in `AgentServiceProvider`
- **Configuration System** - Complete config structure in `config/agents.php`
- **Artisan Commands** - `LifecycleCommand` for management operations
- **Laravel Integration** - Full Laravel service container integration

### 📊 **Features Implemented**

#### ✅ **Agent Creation & Destruction**
- Controlled agent creation with resource limits
- Automatic cleanup of expired agents
- Resource tracking and management
- Lifecycle event recording

#### ✅ **Agent Pooling**
- Efficient agent reuse
- Configurable pool sizes (max_size, min_size)
- Automatic pool management
- Hit/miss statistics with hit rate calculation
- Agent matching based on options and system prompts

#### ✅ **Health Checks**
- Memory usage monitoring with thresholds
- Conversation count tracking
- Responsiveness testing
- Resource consumption analysis
- Cached health check results

#### ✅ **Resource Management**
- Memory usage tracking per agent
- API call monitoring
- Conversation count limits
- Automatic resource cleanup
- Peak memory tracking

#### ✅ **Monitoring & Statistics**
- Lifecycle event tracking
- Health check results
- Resource usage statistics
- Pool performance metrics
- Detailed event logging

## Features

### 🏗️ **Agent Creation & Destruction**
- Controlled agent creation with resource limits
- Automatic cleanup of expired agents
- Resource tracking and management
- Lifecycle event recording

### 🏊 **Agent Pooling**
- Efficient agent reuse
- Configurable pool sizes
- Automatic pool management
- Hit/miss statistics

### 🏥 **Health Checks**
- Memory usage monitoring
- Conversation count tracking
- Responsiveness testing
- Resource consumption analysis

### 📊 **Resource Management**
- Memory usage tracking
- API call monitoring
- Conversation count limits
- Automatic resource cleanup

### 📈 **Monitoring & Statistics**
- Lifecycle event tracking
- Health check results
- Resource usage statistics
- Pool performance metrics

## Architecture

```
AgentLifecycleManager
├── AgentPool (Agent reuse & management)
├── HealthChecker (Health monitoring)
├── LifecycleEvent (Event tracking)
└── ResourceManager (Resource tracking)
```

## Configuration

### Basic Configuration

```php
// config/agents.php
return [
    'lifecycle' => [
        'enabled' => true,
        'max_agents' => 100,
        'max_memory_per_agent' => 50 * 1024 * 1024, // 50MB
        'max_conversations_per_agent' => 1000,
        'agent_ttl' => 3600, // 1 hour
        'health_check_interval' => 300, // 5 minutes
        'cleanup_interval' => 600, // 10 minutes
        'enable_pooling' => true,
        'enable_health_checks' => true,
        'enable_resource_tracking' => true,
        
        'pool' => [
            'max_size' => 50,
            'min_size' => 5,
            'max_idle_time' => 1800, // 30 minutes
            'cleanup_interval' => 300, // 5 minutes
            'enable_stats' => true,
        ],
        
        'health' => [
            'memory_threshold' => 50 * 1024 * 1024, // 50MB
            'conversation_threshold' => 1000,
            'response_timeout' => 5, // seconds
            'enable_caching' => true,
            'cache_ttl' => 300, // 5 minutes
        ],
    ],
];
```

## Usage

### Basic Usage

```php
use Sapiensly\OpenaiAgents\Lifecycle\AgentLifecycleManager;

// Get the lifecycle manager
$lifecycleManager = app(AgentLifecycleManager::class);

// Create an agent with lifecycle management
$agent = $lifecycleManager->createAgent([
    'model' => 'gpt-4',
    'temperature' => 0.7,
], 'You are a helpful assistant.');

// Use the agent
$response = $agent->chat('Hello!');

// Return agent to pool when done
$lifecycleManager->returnAgent($agent);
```

### Pool Management

```php
use Sapiensly\OpenaiAgents\Lifecycle\AgentPool;

// Get the agent pool
$pool = app(AgentPool::class);

// Add agent to pool
$pool->addAgent($agent, ['type' => 'general']);

// Get agent from pool
$agent = $pool->getAgent(['type' => 'general']);

// Return agent to pool
$pool->returnAgent($agent);

// Get pool statistics
$stats = $pool->getStats();
```

### Health Checks

```php
use Sapiensly\OpenaiAgents\Lifecycle\HealthChecker;

// Get the health checker
$healthChecker = app(HealthChecker::class);

// Check single agent health
$health = $healthChecker->checkHealth($agent);

// Check multiple agents
$agents = [$agent1, $agent2, $agent3];
$healthResults = $healthChecker->checkMultipleAgents($agents);

// Get health summary
$summary = $healthChecker->getHealthSummary($agents);
```

### Advanced Usage

```php
// Get lifecycle manager
$lifecycleManager = app(AgentLifecycleManager::class);

// Create agent with metadata
$agent = $lifecycleManager->createAgent(
    ['model' => 'gpt-4'],
    'You are a coding assistant.',
    ['purpose' => 'code_review', 'priority' => 'high']
);

// Perform health checks
$healthChecks = $lifecycleManager->performHealthChecks();

// Get resource usage
$resourceUsage = $lifecycleManager->getResourceUsage();

// Get lifecycle statistics
$stats = $lifecycleManager->getLifecycleStats();

// Clean up expired agents
$cleanedUp = $lifecycleManager->cleanupExpiredAgents();

// Destroy agent when no longer needed
$lifecycleManager->destroyAgent($agent, ['reason' => 'task_completed']);
```

## Health Check Results

### Memory Usage Check
```php
[
    'healthy' => true,
    'usage' => 25165824, // 24MB
    'limit' => 52428800, // 50MB
    'percentage' => 48.0,
]
```

### Conversation Count Check
```php
[
    'healthy' => true,
    'count' => 150,
    'limit' => 1000,
    'percentage' => 15.0,
]
```

### Responsiveness Check
```php
[
    'healthy' => true,
    'response_time' => 2.5, // milliseconds
    'responsive' => true,
    'error' => null,
]
```

## Lifecycle Events

### Event Types
- `agent_created` - Agent was created
- `agent_destroyed` - Agent was destroyed
- `agent_returned` - Agent returned to pool
- `health_check_failed` - Health check failed
- `resource_limit_exceeded` - Resource limit exceeded

### Event Data
```php
[
    'type' => 'agent_created',
    'data' => [
        'agent_id' => 'agent_1234567890_1234567890',
        'options' => ['model' => 'gpt-4'],
        'metadata' => ['purpose' => 'code_review'],
    ],
    'timestamp' => 1234567890,
]
```

## Resource Management

### Memory Tracking
```php
$resourceUsage = $lifecycleManager->getResourceUsage();

// Result:
[
    'memory' => [
        'total' => 104857600, // 100MB
        'average' => 20971520, // 20MB per agent
        'peak' => 52428800, // 50MB peak
    ],
    'api_calls' => [
        'total' => 1500,
        'average' => 30,
    ],
    'conversations' => [
        'total' => 5000,
        'average' => 100,
    ],
]
```

## Monitoring & Statistics

### Lifecycle Statistics
```php
$stats = $lifecycleManager->getLifecycleStats();

// Result:
[
    'total_agents_created' => 150,
    'total_agents_destroyed' => 25,
    'current_pool_size' => 50,
    'health_check_results' => [...],
    'resource_usage' => [...],
]
```

### Pool Statistics
```php
$poolStats = $pool->getStats();

// Result:
[
    'total_created' => 150,
    'total_destroyed' => 25,
    'current_size' => 50,
    'hits' => 1200,
    'misses' => 50,
    'hit_rate' => 96.0, // percentage
    'pool_size' => 50,
    'available_agents' => 35,
]
```

## Integration with Laravel

### Service Provider Registration
The lifecycle management system is automatically registered in the `AgentServiceProvider`:

```php
// Automatically available in your application
$lifecycleManager = app(AgentLifecycleManager::class);
$pool = app(AgentPool::class);
$healthChecker = app(HealthChecker::class);
```

### Artisan Commands
```bash
# Check agent health
php artisan agents:lifecycle health

# Clean up expired agents
php artisan agents:lifecycle cleanup

# Show lifecycle statistics
php artisan agents:lifecycle stats

# Pool information
php artisan agents:lifecycle pool

# With detailed output
php artisan agents:lifecycle health --detailed

# JSON output format
php artisan agents:lifecycle stats --format=json
```

## Best Practices

### 1. **Resource Management**
```php
// Always return agents to pool when done
try {
    $agent = $lifecycleManager->getAgent();
    $response = $agent->chat('Hello');
} finally {
    $lifecycleManager->returnAgent($agent);
}
```

### 2. **Health Monitoring**
```php
// Regular health checks
$healthChecks = $lifecycleManager->performHealthChecks();
foreach ($healthChecks as $agentId => $health) {
    if (!$health['overall_healthy']) {
        Log::warning("Agent {$agentId} is unhealthy", $health);
    }
}
```

### 3. **Resource Limits**
```php
// Monitor resource usage
$usage = $lifecycleManager->getResourceUsage();
if ($usage['memory']['percentage'] > 80) {
    Log::warning('High memory usage detected', $usage);
}
```

### 4. **Pool Management**
```php
// Use pool efficiently
$agent = $pool->getAgent(['model' => 'gpt-4']);
if (!$agent) {
    // Create new agent if none available
    $agent = $lifecycleManager->createAgent(['model' => 'gpt-4']);
}
```

## Troubleshooting

### Common Issues

#### 1. **Memory Exhaustion**
```php
// Check memory usage
$usage = $lifecycleManager->getResourceUsage();
if ($usage['memory']['total'] > $config['max_memory_per_agent'] * $config['max_agents']) {
    // Force cleanup
    $lifecycleManager->cleanupExpiredAgents();
}
```

#### 2. **Unhealthy Agents**
```php
// Check agent health
$health = $healthChecker->checkHealth($agent);
if (!$health['overall_healthy']) {
    // Destroy and recreate
    $lifecycleManager->destroyAgent($agent);
    $agent = $lifecycleManager->createAgent();
}
```

#### 3. **Pool Performance**
```php
// Monitor pool performance
$stats = $pool->getStats();
if ($stats['hit_rate'] < 80) {
    // Consider increasing pool size or optimizing agent reuse
    Log::warning('Low pool hit rate', $stats);
}
```

## Advanced Configuration

### Custom Health Checks
```php
// Extend HealthChecker for custom checks
class CustomHealthChecker extends HealthChecker
{
    protected function checkCustomMetrics(Agent $agent): array
    {
        // Custom health check logic
        return [
            'healthy' => true,
            'custom_metric' => 'value',
        ];
    }
}
```

### Custom Pool Strategies
```php
// Implement custom pooling logic
class CustomAgentPool extends AgentPool
{
    protected function findAvailableAgent(array $criteria): ?Agent
    {
        // Custom agent selection logic
        return parent::findAvailableAgent($criteria);
    }
}
```

## Performance Considerations

### 1. **Memory Management**
- Set appropriate memory limits
- Monitor memory usage regularly
- Clean up expired agents promptly

### 2. **Pool Sizing**
- Balance pool size with memory usage
- Monitor hit rates for optimal sizing
- Adjust based on application load

### 3. **Health Check Frequency**
- Don't check too frequently (performance impact)
- Don't check too rarely (unhealthy agents)
- Use caching for health check results

### 4. **Resource Tracking**
- Enable only necessary tracking
- Use appropriate cache TTLs
- Clean up old statistics periodically

## Examples

### Complete Example
```php
<?php

namespace App\Services;

use Sapiensly\OpenaiAgents\Lifecycle\AgentLifecycleManager;

class AgentService
{
    public function __construct(
        private AgentLifecycleManager $lifecycleManager
    ) {}

    public function processRequest(string $message): string
    {
        // Get agent from lifecycle manager
        $agent = $this->lifecycleManager->getAgent([
            'model' => 'gpt-4',
            'temperature' => 0.7,
        ], 'You are a helpful assistant.');

        try {
            // Process the request
            $response = $agent->chat($message);
            
            return $response;
        } finally {
            // Always return agent to pool
            $this->lifecycleManager->returnAgent($agent);
        }
    }

    public function getSystemHealth(): array
    {
        return [
            'lifecycle_stats' => $this->lifecycleManager->getLifecycleStats(),
            'resource_usage' => $this->lifecycleManager->getResourceUsage(),
            'health_checks' => $this->lifecycleManager->performHealthChecks(),
        ];
    }
}
```

## 🎯 Performance Benefits

1. **Resource Efficiency**
   - Automatic cleanup prevents memory leaks
   - Pooling reduces agent creation overhead
   - Resource limits prevent runaway usage

2. **Health Monitoring**
   - Proactive detection of unhealthy agents
   - Automatic replacement of failed agents
   - Performance metrics tracking

3. **Scalability**
   - Configurable limits for different environments
   - Pool sizing based on load
   - Efficient resource utilization

## 🔍 Monitoring Capabilities

#### Health Check Results
```php
[
    'healthy' => true,
    'memory_usage' => 25165824, // 24MB
    'memory_limit' => 52428800, // 50MB
    'conversation_count' => 150,
    'conversation_limit' => 1000,
    'responsive' => true,
]
```

#### Pool Statistics
```php
[
    'pool_size' => 50,
    'available_agents' => 35,
    'hit_rate' => 96.0, // percentage
    'hits' => 1200,
    'misses' => 50,
]
```

#### Resource Usage
```php
[
    'memory' => [
        'total' => 104857600, // 100MB
        'average' => 20971520, // 20MB per agent
        'peak' => 52428800, // 50MB peak
    ],
    'api_calls' => [
        'total' => 1500,
        'average' => 30,
    ],
]
```

## 🎯 Next Steps

The lifecycle management system is now complete and ready for production use. The implementation provides:

1. **Complete Resource Management** - Automatic cleanup and monitoring
2. **Efficient Pooling** - Reduced overhead and improved performance
3. **Health Monitoring** - Proactive issue detection
4. **Comprehensive Statistics** - Full visibility into system performance
5. **Laravel Integration** - Seamless integration with Laravel applications

This system significantly improves the Python SDK's capabilities by providing enterprise-grade lifecycle management for AI agents in Laravel applications. 