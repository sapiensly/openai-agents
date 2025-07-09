<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tracing;

/**
 * Class TracingManager
 *
 * Manages distributed tracing for handoff operations.
 * Generates unique trace IDs and manages spans for observability.
 */
class TracingManager
{
    /**
     * Current trace ID.
     *
     * @var string|null
     */
    private ?string $traceId = null;

    /**
     * Current span ID.
     *
     * @var string|null
     */
    private ?string $spanId = null;

    /**
     * Span stack for nested operations.
     *
     * @var array
     */
    private array $spanStack = [];

    /**
     * Create a new TracingManager instance.
     *
     * @param string|null $traceId Initial trace ID (optional)
     */
    public function __construct(?string $traceId = null)
    {
        $this->traceId = $traceId ?? $this->generateTraceId();
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
     * @return string
     */
    public function getSpanId(): string
    {
        return $this->spanId ?? $this->generateSpanId();
    }

    /**
     * Start a new span.
     *
     * @param string $operationName The name of the operation
     * @param array $attributes Additional attributes for the span
     * @return string The span ID
     */
    public function startSpan(string $operationName, array $attributes = []): string
    {
        $spanId = $this->generateSpanId();
        $this->spanStack[] = [
            'id' => $spanId,
            'operation' => $operationName,
            'start_time' => microtime(true),
            'attributes' => $attributes,
            'trace_id' => $this->traceId
        ];
        $this->spanId = $spanId;

        // Log span start
        \Log::info('span_started', [
            'trace_id' => $this->traceId,
            'span_id' => $spanId,
            'operation' => $operationName,
            'attributes' => $attributes
        ]);

        return $spanId;
    }

    /**
     * End the current span.
     *
     * @param array $attributes Additional attributes for the span
     * @return array The span data
     */
    public function endSpan(array $attributes = []): array
    {
        if (empty($this->spanStack)) {
            throw new \RuntimeException('No active span to end');
        }

        $span = array_pop($this->spanStack);
        $span['end_time'] = microtime(true);
        $span['duration_ms'] = round(($span['end_time'] - $span['start_time']) * 1000, 2);
        $span['attributes'] = array_merge($span['attributes'], $attributes);

        // Update current span ID
        $this->spanId = !empty($this->spanStack) ? end($this->spanStack)['id'] : null;

        // Log span end
        \Log::info('span_ended', [
            'trace_id' => $this->traceId,
            'span_id' => $span['id'],
            'operation' => $span['operation'],
            'duration_ms' => $span['duration_ms'],
            'attributes' => $span['attributes']
        ]);

        return $span;
    }

    /**
     * Get all completed spans for the current trace.
     *
     * @return array
     */
    public function getCompletedSpans(): array
    {
        return array_filter($this->spanStack, function($span) {
            return isset($span['end_time']);
        });
    }

    /**
     * Create a child span context.
     *
     * @param string $operationName The name of the operation
     * @param array $attributes Additional attributes for the span
     * @return array The span context
     */
    public function createChildSpan(string $operationName, array $attributes = []): array
    {
        $spanId = $this->startSpan($operationName, $attributes);
        return [
            'trace_id' => $this->traceId,
            'span_id' => $spanId,
            'parent_span_id' => $this->spanId
        ];
    }

    /**
     * Inject trace context into headers or metadata.
     *
     * @param array $headers Existing headers
     * @return array Headers with trace context
     */
    public function injectTraceContext(array $headers = []): array
    {
        return array_merge($headers, [
            'X-Trace-ID' => $this->traceId,
            'X-Span-ID' => $this->spanId,
            'X-Trace-Sampled' => '1'
        ]);
    }

    /**
     * Extract trace context from headers or metadata.
     *
     * @param array $headers Headers containing trace context
     * @return array|null Trace context or null if not found
     */
    public static function extractTraceContext(array $headers): ?array
    {
        if (isset($headers['X-Trace-ID'])) {
            return [
                'trace_id' => $headers['X-Trace-ID'],
                'span_id' => $headers['X-Span-ID'] ?? null,
                'sampled' => ($headers['X-Trace-Sampled'] ?? '0') === '1'
            ];
        }
        return null;
    }
} 