<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tracing\Exporters;

use Sapiensly\OpenaiAgents\Tracing\TraceExporterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Class JaegerExporter
 *
 * Exports trace data to Jaeger for distributed tracing visualization.
 */
class JaegerExporter implements TraceExporterInterface
{
    /**
     * The Jaeger endpoint URL.
     */
    private string $endpoint;

    /**
     * Whether the exporter is enabled.
     */
    private bool $enabled;

    /**
     * The service name for Jaeger.
     */
    private string $serviceName;

    /**
     * Create a new JaegerExporter instance.
     *
     * @param string $endpoint The Jaeger endpoint URL
     * @param array $config The exporter configuration
     */
    public function __construct(string $endpoint, array $config = [])
    {
        $this->endpoint = rtrim($endpoint, '/');
        $this->enabled = $config['enabled'] ?? true;
        $this->serviceName = $config['service_name'] ?? 'laravel-openai-agents';
    }

    /**
     * {@inheritdoc}
     */
    public function export(array $traceData): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $jaegerData = $this->convertToJaegerFormat($traceData);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->endpoint . '/api/traces', $jaegerData);

            if (!$response->successful()) {
                Log::warning('JaegerExporter: Failed to send trace data', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('JaegerExporter: Failed to export trace', [
                'error' => $e->getMessage(),
                'trace_id' => $traceData['trace_id'] ?? 'unknown'
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exportSpan(array $span): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $jaegerSpan = $this->convertSpanToJaegerFormat($span);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->endpoint . '/api/spans', [$jaegerSpan]);

            if (!$response->successful()) {
                Log::warning('JaegerExporter: Failed to send span data', [
                    'status' => $response->status(),
                    'span_id' => $span['id'] ?? 'unknown'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('JaegerExporter: Failed to export span', [
                'error' => $e->getMessage(),
                'span_id' => $span['id'] ?? 'unknown'
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exportEvent(array $event): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $jaegerEvent = $this->convertEventToJaegerFormat($event);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->endpoint . '/api/events', [$jaegerEvent]);

            if (!$response->successful()) {
                Log::warning('JaegerExporter: Failed to send event data', [
                    'status' => $response->status(),
                    'event_type' => $event['type'] ?? 'unknown'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('JaegerExporter: Failed to export event', [
                'error' => $e->getMessage(),
                'event_type' => $event['type'] ?? 'unknown'
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'jaeger';
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Convert trace data to Jaeger format.
     *
     * @param array $traceData The trace data
     * @return array The Jaeger format data
     */
    private function convertToJaegerFormat(array $traceData): array
    {
        $spans = [];
        foreach ($traceData['spans'] as $span) {
            $spans[] = $this->convertSpanToJaegerFormat($span);
        }

        return [
            'data' => $spans,
            'total' => count($spans),
            'limit' => count($spans),
            'offset' => 0,
            'errors' => null,
        ];
    }

    /**
     * Convert a span to Jaeger format.
     *
     * @param array $span The span data
     * @return array The Jaeger format span
     */
    private function convertSpanToJaegerFormat(array $span): array
    {
        $startTime = (int)($span['start_time'] * 1000000); // Convert to microseconds
        $duration = isset($span['duration']) ? (int)($span['duration'] * 1000000) : 0;

        $jaegerSpan = [
            'traceID' => $this->convertTraceId($span['trace_id']),
            'spanID' => $this->convertSpanId($span['id']),
            'operationName' => $span['name'],
            'startTime' => $startTime,
            'duration' => $duration,
            'tags' => $this->convertAttributesToTags($span['attributes'] ?? []),
            'logs' => [],
            'processID' => $this->serviceName,
            'warnings' => null,
        ];

        if (isset($span['parent_span_id'])) {
            $jaegerSpan['references'] = [
                [
                    'refType' => 'CHILD_OF',
                    'traceID' => $this->convertTraceId($span['trace_id']),
                    'spanID' => $this->convertSpanId($span['parent_span_id']),
                ]
            ];
        }

        return $jaegerSpan;
    }

    /**
     * Convert an event to Jaeger format.
     *
     * @param array $event The event data
     * @return array The Jaeger format event
     */
    private function convertEventToJaegerFormat(array $event): array
    {
        return [
            'traceID' => $this->convertTraceId($event['trace_id']),
            'spanID' => $this->convertSpanId($event['span_id']),
            'operationName' => $event['type'],
            'startTime' => (int)(strtotime($event['timestamp']) * 1000000),
            'duration' => 0,
            'tags' => $this->convertAttributesToTags($event['data'] ?? []),
            'logs' => [],
            'processID' => $this->serviceName,
        ];
    }

    /**
     * Convert trace ID to Jaeger format.
     *
     * @param string $traceId The trace ID
     * @return string The Jaeger format trace ID
     */
    private function convertTraceId(string $traceId): string
    {
        // Convert to 16-character hex string
        return substr(md5($traceId), 0, 16);
    }

    /**
     * Convert span ID to Jaeger format.
     *
     * @param string $spanId The span ID
     * @return string The Jaeger format span ID
     */
    private function convertSpanId(string $spanId): string
    {
        // Convert to 16-character hex string
        return substr(md5($spanId), 0, 16);
    }

    /**
     * Convert attributes to Jaeger tags.
     *
     * @param array $attributes The attributes
     * @return array The Jaeger tags
     */
    private function convertAttributesToTags(array $attributes): array
    {
        $tags = [];
        
        foreach ($attributes as $key => $value) {
            $tags[] = [
                'key' => $key,
                'type' => is_numeric($value) ? 'int64' : 'string',
                'value' => is_array($value) ? json_encode($value) : $value,
            ];
        }

        return $tags;
    }
} 