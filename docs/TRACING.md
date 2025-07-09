# Tracing - Observability for OpenAI Agents

The Tracing system in the OpenAI Agents package provides comprehensive observability into agent conversations. It enables logging, monitoring, and analyzing the interactions between your application and AI agents.

## Overview

The Tracing System provides comprehensive observability for OpenAI Agents, enabling detailed monitoring, debugging, and analysis of agent conversations, tool executions, and handoff operations.

### 🚀 **Advanced Features**

The Advanced Tracing System extends the basic tracing functionality with:

- **Distributed Tracing**: Track operations across multiple services
- **Multiple Exporters**: Send traces to databases, Jaeger, Zipkin, and custom systems
- **Detailed Metrics**: Comprehensive performance and usage metrics
- **Error Tracking**: Automatic error detection and reporting
- **Sampling Control**: Configurable sampling rates for performance
- **Real-time Monitoring**: Live trace visualization and analysis

## What is Tracing?

Tracing is a monitoring system that records and tracks every event during the execution of a Runner. Think of it as a "black box" that logs each step of the conversation, allowing you to inspect what happened, why, and when.

## Configuration

### Basic Configuration

Enabling tracing is simple. In your `config/agents.php` file, add the following configuration:

```php
return [
    // Other configuration options...
    'tracing' => [
        'enabled' => true,
        'processors' => [
            fn(array $record) => logger()->info('agent trace', $record),
            // Add more processors here
        ],
    ],
];
```

### Advanced Configuration

```php
'tracing' => [
    'enabled' => true,
    'sampling_rate' => 0.1, // 10% sampling for high traffic
    'export_to_database' => true,
    'export_to_logs' => true,
    'jaeger_endpoint' => env('JAEGER_ENDPOINT'),
    'zipkin_endpoint' => env('ZIPKIN_ENDPOINT'),
    'processors' => [
        // Custom trace processors
        function(array $record) {
            // Process trace records
        },
    ],
    'exporters' => [
        'database' => [
            'enabled' => true,
            'connection' => 'mysql',
        ],
        'jaeger' => [
            'enabled' => true,
            'endpoint' => env('JAEGER_ENDPOINT'),
            'service_name' => 'laravel-openai-agents',
        ],
        'custom' => [
            'enabled' => true,
            'class' => CustomExporter::class,
            'config' => [
                'endpoint' => env('CUSTOM_TRACING_ENDPOINT'),
            ],
        ],
    ],
],
```

## Trace Record Structure

When enabled, the tracing system will emit several types of records:

1. **Span Start**: When a conversation begins
   ```json
   {
     "type": "span_start",
     "span_id": "conv_123456",
     "max_turns": 5,
     "timestamp": "2024-01-15T10:30:00Z"
   }
   ```

2. **Turn**: For each interaction in the conversation
   ```json
   {
     "type": "turn",
     "span_id": "conv_123456",
     "turn": 1,
     "input": "What's the weather like?",
     "output": "I'll check the weather for you. [[tool:get_weather]]",
     "timestamp": "2024-01-15T10:30:02Z"
   }
   ```

3. **Span End**: When the conversation completes
   ```json
   {
     "type": "span_end",
     "span_id": "conv_123456",
     "total_turns": 2,
     "duration": 5.2,
     "timestamp": "2024-01-15T10:30:05Z"
   }
   ```

## Advanced Tracing Features

### 1. Distributed Tracing

Track operations across your entire system:

```php
// Start a trace
$tracing = app(AdvancedTracing::class);
$spanId = $tracing->startSpan('agent_conversation', [
    'user_id' => $userId,
    'agent_type' => 'customer_support',
    'priority' => 'high'
]);

// Record operations
$tracing->recordEvent($spanId, [
    'type' => 'tool_call',
    'tool_name' => 'get_weather',
    'duration' => 0.5
]);

// End the trace
$tracing->endSpan($spanId, ['success' => true]);
```

### 2. Multiple Exporters

Send traces to different systems:

```php
// Database exporter
$tracing->registerExporter('database', new DatabaseExporter());

// Jaeger exporter
$tracing->registerExporter('jaeger', new JaegerExporter('http://jaeger:14268'));

// Custom exporter
$tracing->registerExporter('custom', new CustomExporter());
```

### 3. Detailed Metrics

Automatic collection of comprehensive metrics:

- **Performance Metrics**: Duration, throughput, latency
- **Usage Metrics**: Tool calls, handoffs, cache hits/misses
- **Error Metrics**: Error rates, failure patterns
- **Resource Metrics**: Memory usage, API calls

### 4. Error Tracking

Automatic error detection and reporting:

```php
try {
    $result = $agent->chat($message);
} catch (\Exception $e) {
    $tracing->recordError($e, [
        'user_id' => $userId,
        'message' => $message,
        'agent_id' => $agent->getId()
    ]);
    throw $e;
}
```

## Exporters

### Database Exporter

Stores traces in database tables for analysis:

```php
// Automatic table creation
Schema::create('agent_traces', function ($table) {
    $table->id();
    $table->string('trace_id')->unique();
    $table->integer('spans_count');
    $table->float('total_duration');
    $table->integer('tool_calls');
    $table->integer('handoffs');
    $table->integer('errors');
    $table->timestamps();
});

// Query traces
$traces = DB::table('agent_traces')
    ->where('created_at', '>=', now()->subDays(7))
    ->orderBy('total_duration', 'desc')
    ->get();
```

### Jaeger Exporter

Sends traces to Jaeger for distributed tracing visualization:

```php
// Configure Jaeger
$tracing->registerExporter('jaeger', new JaegerExporter(
    'http://jaeger:14268',
    [
        'service_name' => 'laravel-openai-agents',
        'enabled' => true,
    ]
));
```

### Custom Exporters

Create custom exporters for specific needs:

```php
class CustomExporter implements TraceExporterInterface
{
    public function export(array $traceData): void
    {
        // Send to custom monitoring system
        $client = new CustomMonitoringClient();
        $client->sendTrace($traceData);
    }

    public function exportSpan(array $span): void
    {
        // Send individual span
        $client = new CustomMonitoringClient();
        $client->sendSpan($span);
    }

    public function exportEvent(array $event): void
    {
        // Send individual event
        $client = new CustomMonitoringClient();
        $client->sendEvent($event);
    }

    public function getName(): string
    {
        return 'custom';
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
```

## Practical Use Cases

### 1. Debugging and Development

```php
// In config/agents.php
'tracing' => [
    'enabled' => true,
    'processors' => [
        // Log everything for debugging
        fn(array $record) => logger()->debug('Agent Execution', $record),
    ],
];
```

**Benefits**:
- See which tools were executed
- Track how many turns the conversation took
- View what responses the agent generated at each step
- Identify errors in guardrails

### 2. Production Monitoring

```php
'tracing' => [
    'enabled' => true,
    'processors' => [
        // Send metrics to a monitoring system
        function(array $record) {
            if ($record['type'] === 'span_end') {
                // Record execution time
                Metrics::timing('agent.execution_time', $record['duration']);
                Metrics::increment('agent.conversations_completed');
            }
        },
    ],
];
```

**Benefits**:
- Monitor average conversation execution time
- Track number of tools used per conversation
- Detect errors or failures in execution

### 3. Cost Analysis

```php
'tracing' => [
    'enabled' => true,
    'processors' => [
        function(array $record) {
            if ($record['type'] === 'turn') {
                // Estimate tokens used based on input/output
                $inputTokens = str_word_count($record['input']) * 1.3; // Estimation
                $outputTokens = str_word_count($record['output']) * 1.3;

                logger()->info('Token Usage', [
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'estimated_cost' => ($inputTokens * 0.00001) + ($outputTokens * 0.00003)
                ]);
            }
        },
    ],
];
```

**Benefits**:
- Track token usage per conversation
- Identify high-resource-consuming conversations
- Optimize prompts to reduce costs

### 4. Audit and Compliance

```php
'tracing' => [
    'enabled' => true,
    'processors' => [
        function(array $record) {
            // Store in database for audit
            DB::table('agent_audit_log')->insert([
                'session_id' => $record['span_id'] ?? null,
                'turn_number' => $record['turn'] ?? null,
                'user_input' => $record['input'] ?? null,
                'agent_output' => $record['output'] ?? null,
                'tools_used' => json_encode($record['tools_used'] ?? []),
                'created_at' => now(),
            ]);
        },
    ],
];
```

**Benefits**:
- Maintain records of all interactions
- Track what data was processed
- Demonstrate transparency in automated decisions

### 5. User Behavior Analysis

```php
'tracing' => [
    'enabled' => true,
    'processors' => [
        function(array $record) {
            if ($record['type'] === 'span_end') {
                // Analyze usage patterns
                Analytics::track('agent_conversation_completed', [
                    'total_turns' => $record['turns_taken'],
                    'tools_used' => count($record['tools_invoked'] ?? []),
                    'conversation_length' => $record['duration'],
                    'user_satisfaction' => $this->calculateSatisfaction($record),
                ]);
            }
        },
    ],
];
```

**Benefits**:
- Identify conversations that take too many turns
- See which tools are most used
- Optimize conversation flow

### 6. Real-Time Alerting

```php
'tracing' => [
    'enabled' => true,
    'processors' => [
        function(array $record) {
            // Alert if something goes wrong
            if ($record['type'] === 'error') {
                Notification::route('slack', '#alerts')
                    ->notify(new AgentErrorAlert($record));
            }

            if (isset($record['turn']) && $record['turn'] > 8) {
                // Alert if a conversation takes too many turns
                Notification::route('email', 'admin@example.com')
                    ->notify(new LongConversationAlert($record));
            }
        },
    ],
];
```

**Benefits**:
- Detect errors immediately
- Identify problematic conversations
- Maintain service quality

## Usage Examples

### 1. Basic Agent Tracing

```php
use Sapiensly\OpenaiAgents\AdvancedTracing;

$tracing = app(AdvancedTracing::class);

// Start trace
$spanId = $tracing->startSpan('agent_conversation', [
    'user_id' => $userId,
    'agent_type' => 'support'
]);

try {
    // Run agent
    $response = $agent->chat($message);
    
    // Record success
    $tracing->recordEvent($spanId, [
        'type' => 'agent_response',
        'response_length' => strlen($response),
        'success' => true
    ]);
    
} catch (\Exception $e) {
    // Record error
    $tracing->recordError($e, [
        'user_id' => $userId,
        'message' => $message
    ]);
    throw $e;
} finally {
    // End trace
    $tracing->endSpan($spanId);
}
```

### 2. Tool Execution Tracing

```php
$runner->registerFunctionTool('get_weather', function($args) use ($tracing) {
    $spanId = $tracing->startSpan('tool_execution', [
        'tool_name' => 'get_weather',
        'arguments' => $args
    ]);
    
    try {
        $startTime = microtime(true);
        $result = $this->weatherService->getWeather($args['city']);
        $duration = microtime(true) - $startTime;
        
        $tracing->recordToolCall('get_weather', $args, $result, $duration);
        
        return $result;
    } finally {
        $tracing->endSpan($spanId);
    }
});
```

### 3. Handoff Tracing

```php
$tracing->recordHandoff(
    $sourceAgent->getId(),
    $targetAgent->getId(),
    $context,
    $success
);
```

### 4. Cache Operation Tracing

```php
$tracing->recordCacheOperation('hit', $cacheKey, $duration);
$tracing->recordCacheOperation('miss', $cacheKey, $duration);
```

## Custom Trace Processors

You can create custom trace processors to handle trace records in special ways:

```php
class CustomTraceProcessor
{
    public function __invoke(array $record)
    {
        // Process the trace record
        // For example, send to a third-party service
        $client = new SomeMonitoringServiceClient();
        $client->sendTrace($record);
    }
}

// In config/agents.php
'tracing' => [
    'enabled' => true,
    'processors' => [
        new CustomTraceProcessor(),
    ],
],
```

## Built-in Processors

The package includes some built-in processors:

```php
// HTTP processor sends trace data to an endpoint
use Sapiensly\OpenaiAgents\Tracing\HttpProcessor;

'processors' => [
    new HttpProcessor('https://your-monitoring-service.com/api/traces'),
],
```

## Monitoring and Analysis

### 1. Performance Monitoring

```php
// Get trace metrics
$metrics = $tracing->getMetrics();

echo "Total spans: " . $metrics['total_spans'] . "\n";
echo "Total duration: " . $metrics['total_duration'] . "s\n";
echo "Tool calls: " . $metrics['tool_calls'] . "\n";
echo "Handoffs: " . $metrics['handoffs'] . "\n";
echo "Errors: " . $metrics['errors'] . "\n";
echo "Cache hit rate: " . ($metrics['cache_hits'] / ($metrics['cache_hits'] + $metrics['cache_misses'])) * 100 . "%\n";
```

### 2. Database Analysis

```sql
-- Find slowest traces
SELECT trace_id, total_duration, tool_calls, handoffs, errors
FROM agent_traces
WHERE created_at >= NOW() - INTERVAL 24 HOUR
ORDER BY total_duration DESC
LIMIT 10;

-- Find traces with errors
SELECT trace_id, errors, total_duration
FROM agent_traces
WHERE errors > 0
ORDER BY created_at DESC;

-- Tool usage statistics
SELECT 
    JSON_EXTRACT(attributes, '$.tool_name') as tool_name,
    COUNT(*) as usage_count,
    AVG(duration) as avg_duration
FROM agent_spans
WHERE name = 'tool_execution'
GROUP BY tool_name
ORDER BY usage_count DESC;
```

### 3. Real-time Monitoring

```php
// Create a real-time monitor
class TraceMonitor
{
    public function monitorTraces(): void
    {
        $tracing = app(AdvancedTracing::class);
        
        // Monitor for slow traces
        if ($tracing->getMetrics()['total_duration'] > 10) {
            $this->alertSlowTrace($tracing->getTraceId());
        }
        
        // Monitor for errors
        if ($tracing->getMetrics()['errors'] > 0) {
            $this->alertError($tracing->getTraceId());
        }
        
        // Monitor cache performance
        $hitRate = $tracing->getMetrics()['cache_hits'] / 
                   ($tracing->getMetrics()['cache_hits'] + $tracing->getMetrics()['cache_misses']);
        
        if ($hitRate < 0.8) {
            $this->alertLowCacheHitRate($hitRate);
        }
    }
}
```

## Integration with External Systems

### 1. Jaeger Integration

```php
// Configure Jaeger
$tracing->registerExporter('jaeger', new JaegerExporter(
    'http://jaeger:14268',
    [
        'service_name' => 'laravel-openai-agents',
        'enabled' => true,
    ]
));

// View traces in Jaeger UI
// http://jaeger:16686
```

### 2. Prometheus Integration

```php
class PrometheusExporter implements TraceExporterInterface
{
    public function export(array $traceData): void
    {
        // Export metrics to Prometheus
        $this->counter('agent_traces_total')->inc();
        $this->histogram('agent_trace_duration')->observe($traceData['metrics']['total_duration']);
        $this->counter('agent_tool_calls_total')->inc($traceData['metrics']['tool_calls']);
    }
}
```

### 3. Elasticsearch Integration

```php
class ElasticsearchExporter implements TraceExporterInterface
{
    public function export(array $traceData): void
    {
        // Send to Elasticsearch for log analysis
        $client = new ElasticsearchClient();
        $client->index([
            'index' => 'agent-traces',
            'body' => $traceData
        ]);
    }
}
```

## Interactive Example with Tinker

Here's a complete example you can run in Laravel Tinker to see tracing in action with a complex conversation flow:

```php
// Create a trace processor that captures and displays records
$traceRecords = [];
$detailedTraceProcessor = function(array $record) use (&$traceRecords) {
    $traceRecords[] = $record;

    echo "🔥 " . strtoupper($record['type']) . " ";

    if ($record['type'] === 'start_span') {
        echo "- Starting conversation (Max turns: " . ($record['attributes']['max_turns'] ?? 'N/A') . ")\n";
    }

    if ($record['type'] === 'event') {
        echo "- Turn {$record['turn']}\n";
        echo "   📥 Input: " . substr($record['input'] ?? '', 0, 60) . "...\n";
        echo "   📤 Output: " . substr($record['output'] ?? '', 0, 60) . "...\n";

        // Detect special patterns
        $output = $record['output'] ?? '';
        if (str_contains($output, '[[tool:')) {
            echo "   🔧 TOOL DETECTED!\n";
        }
    }

    if ($record['type'] === 'end_span') {
        echo "- Conversation completed\n";
    }

    echo str_repeat("-", 50) . "\n";
};

// Create tracing and runner
$detailedTracing = new \Sapiensly\OpenaiAgents\Tracing\Tracing([$detailedTraceProcessor]);
$manager = app(\Sapiensly\OpenaiAgents\AgentManager::class);

// Create agent with system prompt that encourages tool usage
$agent = $manager->agent([], 'You are a helpful assistant. When users ask about weather, you MUST use the get_weather function to get accurate information.');

$toolRunner = new \Sapiensly\OpenaiAgents\Runner($agent, 5, $detailedTracing);

// Register weather tool
$toolRunner->registerFunctionTool('get_weather', function($args) {
    $city = $args['city'] ?? 'Unknown City';
    echo "   ⚡ EXECUTING WEATHER TOOL for: {$city}\n";

    $weather = [
        'Barcelona' => 'Temperature: 24°C, Condition: Sunny, Humidity: 65%, Wind: 10 km/h',
        'Madrid' => 'Temperature: 28°C, Condition: Clear, Humidity: 45%, Wind: 5 km/h',
    ];

    return $weather[$city] ?? "Temperature: 22°C, Condition: Variable, Humidity: 60%";
}, [
    'name' => 'get_weather',
    'description' => 'Get current weather information for any city',
    'schema' => [
        'type' => 'object',
        'properties' => [
            'city' => [
                'type' => 'string', 
                'description' => 'The name of the city to get weather for'
            ]
        ],
        'required' => ['city']
    ]
]);

// Register travel tip tool
$toolRunner->registerFunctionTool('get_travel_tip', function($args) {
    $city = $args['city'] ?? 'Unknown City';

    $tips = [
        'Barcelona' => 'Visit Sagrada Familia early morning. Try tapas in El Born district!',
        'Madrid' => 'Visit Prado Museum on Sunday evenings for free entry.'
    ];

    return $tips[$city] ?? "Explore local markets and try regional cuisine.";
}, [
    'name' => 'get_travel_tip',
    'description' => 'Get travel tips for a city',
    'schema' => [
        'type' => 'object',
        'properties' => [
            'city' => ['type' => 'string', 'description' => 'The city name']
        ],
        'required' => ['city']
    ]
]);

echo "🚀 STARTING CONVERSATION WITH TOOLS...\n";

// Message that should trigger multiple tools
$message = "I'm planning a trip to Barcelona next week. Can you check the weather and give me some travel tips?";

echo "🎯 QUESTION: {$message}\n";

$result = $toolRunner->run($message);

echo "✅ FINAL RESULT:\n{$result}\n";

// Analyze the traces
echo "\n🔍 TRACE ANALYSIS:\n";
echo "  Total Records: " . count($traceRecords) . "\n";

$toolCalls = [];
foreach ($traceRecords as $record) {
    if ($record['type'] === 'event') {
        $output = $record['output'] ?? '';
        if (preg_match('/\[\[tool:(\w+)/', $output, $matches)) {
            $toolCalls[] = $matches[1];
        }
    }
}

echo "  Tool Calls: " . count($toolCalls) . "\n";
echo "  Tools Used: " . implode(', ', array_unique($toolCalls)) . "\n";
```

This example demonstrates:

1. Creating a custom trace processor that displays detailed information
2. Registering multiple tools with the Runner
3. Running a complex conversation that uses tools
4. Analyzing the trace records to extract insights

When you run this in Tinker, you'll see a real-time trace of the conversation including:
- When the span starts
- Each turn with input and output
- Tool invocations
- The final response
- Analysis of the entire trace

## Best Practices

### 1. Sampling Strategy

```php
// High traffic: Sample 10%
'sampling_rate' => 0.1,

// Development: Sample 100%
'sampling_rate' => 1.0,

// Production: Sample based on error rate
'sampling_rate' => $this->calculateSamplingRate(),
```

### 2. Performance Optimization

```php
// Async export for high performance
$tracing->registerExporter('async', new AsyncExporter());

// Batch export for efficiency
$tracing->registerExporter('batch', new BatchExporter());
```

### 3. Security Considerations

```php
// Sanitize sensitive data
$tracing->registerExporter('sanitized', new SanitizedExporter([
    'remove_fields' => ['password', 'token', 'secret'],
    'mask_fields' => ['email', 'phone'],
]));
```

### 4. Error Handling

```php
// Graceful degradation
$tracing->registerExporter('fallback', new FallbackExporter([
    'primary' => new JaegerExporter($endpoint),
    'fallback' => new DatabaseExporter(),
]));
```

### 5. General Best Practices

1. **Enable in Development**: Always enable tracing during development to understand agent behavior
2. **Sample in Production**: In high-volume production, consider sampling trace data (e.g., only trace 10% of conversations)
3. **Secure Sensitive Data**: Remove or mask sensitive data before storing trace records
4. **Set Retention Policies**: Establish how long trace data should be kept
5. **Monitor Processor Performance**: Ensure your trace processors don't slow down the application

## Troubleshooting

### Common Issues

1. **High Memory Usage**: Reduce sampling rate or use async exporters
2. **Slow Performance**: Use batch exporters or disable tracing in high-traffic scenarios
3. **Missing Traces**: Check sampling rate and exporter configuration
4. **Export Failures**: Verify network connectivity and endpoint URLs

### Debug Commands

```bash
# Check trace configuration
php artisan tinker
>>> app(AdvancedTracing::class)->getMetrics()

# Test exporters
php artisan tinker
>>> app(AdvancedTracing::class)->exportTrace()

# View database traces
php artisan tinker
>>> DB::table('agent_traces')->latest()->first()
```

## Conclusion

Tracing is essential for any serious application using AI agents. It provides complete visibility into system behavior, helps optimize costs, detect problems, and fulfill audit requirements.

The Advanced Tracing System provides comprehensive observability for OpenAI Agents, enabling detailed monitoring, debugging, and analysis. With multiple exporters, detailed metrics, and integration capabilities, it transforms your AI agents from black boxes into transparent, observable systems.

By implementing proper tracing, you can:

- **Monitor Performance**: Track response times and throughput
- **Debug Issues**: Identify problems quickly with detailed traces
- **Optimize Costs**: Analyze token usage and API calls
- **Ensure Quality**: Monitor error rates and success metrics
- **Comply with Regulations**: Maintain audit trails for compliance

By implementing proper tracing, you transform your AI agents from black boxes into transparent, observable systems that you can monitor, debug, and improve over time.
