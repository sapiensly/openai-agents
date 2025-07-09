<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tracing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class AdvancedTracing
 *
 * Advanced tracing system that provides comprehensive observability
 * for agent conversations, tool executions, and handoff operations.
 * Extends the basic Tracing class with distributed tracing, metrics,
 * and integration capabilities.
 */
class AdvancedTracing extends Tracing
{
    /**
     * The trace ID for the current operation.
     */
    private string $traceId;

    /**
     * The current span ID.
     */
    private ?string $currentSpanId = null;

    /**
     * The span stack for nested operations.
     */
    private array $spanStack = [];

    /**
     * Metrics for the current trace.
     */
    private array $metrics = [
        'total_spans' => 0,
        'total_duration' => 0.0,
        'errors' => 0,
        'tool_calls' => 0,
        'handoffs' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
    ];

    /**
     * Whether tracing is enabled.
     */
    private bool $enabled;

    /**
     * The sampling rate (0.0 to 1.0).
     */
    private float $samplingRate;

    /**
     * Exporters for trace data.
     */
    private array $exporters = [];

    /**
     * Create a new AdvancedTracing instance.
     *
     * @param array $processors The trace processors
     * @param array $config The configuration
     */
    public function __construct(array $processors = [], array $config = [])
    {
        parent::__construct($processors);

        $this->enabled = $config['enabled'] ?? true;
        $this->samplingRate = $config['sampling_rate'] ?? 1.0;
        $this->traceId = $this->generateTraceId();

        // Register default exporters
        $this->registerDefaultExporters($config);
    }

    /**
     * Generate a unique trace ID.
     *
     * @return string
     */
    private function generateTraceId(): string
    {
        return uniqid('trace_', true);
    }

    /**
     * Generate a unique span ID.
     *
     * @return string
     */
    private function generateSpanId(): string
    {
        return uniqid('span_', true);
    }

    /**
     * Register default exporters.
     *
     * @param array $config The configuration
     */
    private function registerDefaultExporters(array $config): void
    {
        // Database exporter
        if ($config['export_to_database'] ?? false) {
            $this->registerExporter('database', new DatabaseExporter());
        }

        // HTTP exporter
        if (isset($config['http_endpoint'])) {
            $this->registerExporter('http', new HttpExporter($config['http_endpoint']));
        }

        // Log exporter
        if ($config['export_to_logs'] ?? true) {
            $this->registerExporter('log', new LogExporter());
        }

        // Jaeger exporter
        if (isset($config['jaeger_endpoint'])) {
            $this->registerExporter('jaeger', new JaegerExporter($config['jaeger_endpoint']));
        }

        // Zipkin exporter
        if (isset($config['zipkin_endpoint'])) {
            $this->registerExporter('zipkin', new ZipkinExporter($config['zipkin_endpoint']));
        }
    }

    /**
     * Register a trace exporter.
     *
     * @param string $name The exporter name
     * @param TraceExporterInterface $exporter The exporter instance
     * @return self
     */
    public function registerExporter(string $name, TraceExporterInterface $exporter): self
    {
        $this->exporters[$name] = $exporter;
        return $this;
    }

    /**
     * Start a new span with advanced features.
     *
     * @param string $name The span name
     * @param array $attributes The span attributes
     * @return string The span ID
     */
    public function startSpan(string $name, array $attributes = []): string
    {
        if (!$this->enabled || !$this->shouldSample()) {
            return parent::startSpan($name, $attributes);
        }

        $spanId = $this->generateSpanId();
        $this->currentSpanId = $spanId;

        $span = [
            'id' => $spanId,
            'trace_id' => $this->traceId,
            'name' => $name,
            'start_time' => microtime(true),
            'attributes' => $attributes,
            'parent_span_id' => $this->currentSpanId,
        ];

        $this->spanStack[] = $span;
        $this->metrics['total_spans']++;

        // Dispatch span start event
        $this->dispatch([
            'type' => 'span_start',
            'span_id' => $spanId,
            'trace_id' => $this->traceId,
            'name' => $name,
            'attributes' => $attributes,
            'timestamp' => now()->toISOString(),
        ]);

        return $spanId;
    }

    /**
     * End the current span.
     *
     * @param string $id The span ID
     * @param array $attributes Additional attributes
     * @return void
     */
    public function endSpan(string $id, array $attributes = []): void
    {
        if (!$this->enabled) {
            parent::endSpan($id);
            return;
        }

        $span = $this->findSpan($id);
        if (!$span) {
            Log::warning("Attempted to end non-existent span: {$id}");
            return;
        }

        $endTime = microtime(true);
        $duration = $endTime - $span['start_time'];

        $span['end_time'] = $endTime;
        $span['duration'] = $duration;
        $span['attributes'] = array_merge($span['attributes'], $attributes);

        $this->metrics['total_duration'] += $duration;

        // Update metrics based on span type
        $this->updateMetricsFromSpan($span);

        // Dispatch span end event
        $this->dispatch([
            'type' => 'span_end',
            'span_id' => $id,
            'trace_id' => $this->traceId,
            'duration' => $duration,
            'attributes' => $span['attributes'],
            'timestamp' => now()->toISOString(),
        ]);

        // Export span data
        $this->exportSpan($span);

        // Remove span from stack
        $this->removeSpan($id);
    }

    /**
     * Record an event with advanced context.
     *
     * @param string $id The span ID
     * @param array $data The event data
     * @return void
     */
    public function recordEvent(string $id, array $data): void
    {
        if (!$this->enabled) {
            parent::recordEvent($id, $data);
            return;
        }

        $event = [
            'type' => 'event',
            'span_id' => $id,
            'trace_id' => $this->traceId,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        $this->dispatch($event);
        $this->exportEvent($event);
    }

    /**
     * Record a tool call with detailed information.
     *
     * @param string $toolName The tool name
     * @param array $arguments The tool arguments
     * @param mixed $result The tool result
     * @param float $duration The execution duration
     * @return void
     */
    public function recordToolCall(string $toolName, array $arguments, mixed $result, float $duration): void
    {
        $this->recordEvent($this->currentSpanId ?? 'unknown', [
            'type' => 'tool_call',
            'tool_name' => $toolName,
            'arguments' => $arguments,
            'result' => $result,
            'duration' => $duration,
            'success' => !is_null($result),
        ]);

        $this->metrics['tool_calls']++;
    }

    /**
     * Record a handoff operation.
     *
     * @param string $sourceAgent The source agent
     * @param string $targetAgent The target agent
     * @param array $context The handoff context
     * @param bool $success Whether the handoff was successful
     * @return void
     */
    public function recordHandoff(string $sourceAgent, string $targetAgent, array $context, bool $success): void
    {
        $this->recordEvent($this->currentSpanId ?? 'unknown', [
            'type' => 'handoff',
            'source_agent' => $sourceAgent,
            'target_agent' => $targetAgent,
            'context_size' => count($context),
            'success' => $success,
        ]);

        $this->metrics['handoffs']++;
    }

    /**
     * Record cache operations.
     *
     * @param string $operation The cache operation (hit/miss)
     * @param string $key The cache key
     * @param float $duration The operation duration
     * @return void
     */
    public function recordCacheOperation(string $operation, string $key, float $duration): void
    {
        $this->recordEvent($this->currentSpanId ?? 'unknown', [
            'type' => 'cache_operation',
            'operation' => $operation,
            'key' => $key,
            'duration' => $duration,
        ]);

        if ($operation === 'hit') {
            $this->metrics['cache_hits']++;
        } else {
            $this->metrics['cache_misses']++;
        }
    }

    /**
     * Record an error with context.
     *
     * @param \Throwable $error The error
     * @param array $context Additional context
     * @return void
     */
    public function recordError(\Throwable $error, array $context = []): void
    {
        $this->recordEvent($this->currentSpanId ?? 'unknown', [
            'type' => 'error',
            'error_class' => get_class($error),
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
            'context' => $context,
        ]);

        $this->metrics['errors']++;
    }

    /**
     * Get the current trace ID.
     *
     * @return string
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /**
     * Get the current span ID.
     *
     * @return string|null
     */
    public function getCurrentSpanId(): ?string
    {
        return $this->currentSpanId;
    }

    /**
     * Get trace metrics.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Get all spans for the current trace.
     *
     * @return array
     */
    public function getSpans(): array
    {
        return $this->spanStack;
    }

    /**
     * Export trace data to all registered exporters.
     *
     * @return void
     */
    public function exportTrace(): void
    {
        if (!$this->enabled) {
            return;
        }

        $traceData = [
            'trace_id' => $this->traceId,
            'spans' => $this->spanStack,
            'metrics' => $this->metrics,
            'timestamp' => now()->toISOString(),
        ];

        foreach ($this->exporters as $name => $exporter) {
            try {
                $exporter->export($traceData);
            } catch (\Exception $e) {
                Log::error("Failed to export trace to {$name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if the trace should be sampled.
     *
     * @return bool
     */
    private function shouldSample(): bool
    {
        return rand(1, 100) <= ($this->samplingRate * 100);
    }

    /**
     * Find a span by ID.
     *
     * @param string $id The span ID
     * @return array|null
     */
    private function findSpan(string $id): ?array
    {
        foreach ($this->spanStack as $span) {
            if ($span['id'] === $id) {
                return $span;
            }
        }
        return null;
    }

    /**
     * Remove a span from the stack.
     *
     * @param string $id The span ID
     * @return void
     */
    private function removeSpan(string $id): void
    {
        $this->spanStack = array_filter($this->spanStack, function($span) use ($id) {
            return $span['id'] !== $id;
        });
    }

    /**
     * Update metrics based on span attributes.
     *
     * @param array $span The span data
     * @return void
     */
    private function updateMetricsFromSpan(array $span): void
    {
        $attributes = $span['attributes'] ?? [];

        if (isset($attributes['tool_call'])) {
            $this->metrics['tool_calls']++;
        }

        if (isset($attributes['handoff'])) {
            $this->metrics['handoffs']++;
        }

        if (isset($attributes['error'])) {
            $this->metrics['errors']++;
        }
    }

    /**
     * Export a span to all exporters.
     *
     * @param array $span The span data
     * @return void
     */
    private function exportSpan(array $span): void
    {
        foreach ($this->exporters as $name => $exporter) {
            try {
                $exporter->exportSpan($span);
            } catch (\Exception $e) {
                Log::error("Failed to export span to {$name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Export an event to all exporters.
     *
     * @param array $event The event data
     * @return void
     */
    private function exportEvent(array $event): void
    {
        foreach ($this->exporters as $name => $exporter) {
            try {
                $exporter->exportEvent($event);
            } catch (\Exception $e) {
                Log::error("Failed to export event to {$name}: " . $e->getMessage());
            }
        }
    }
} 