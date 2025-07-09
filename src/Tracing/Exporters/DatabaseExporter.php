<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tracing\Exporters;

use Sapiensly\OpenaiAgents\Tracing\TraceExporterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class DatabaseExporter
 *
 * Exports trace data to database tables for analysis and monitoring.
 */
class DatabaseExporter implements TraceExporterInterface
{
    /**
     * Whether the exporter is enabled.
     */
    private bool $enabled;

    /**
     * Create a new DatabaseExporter instance.
     *
     * @param bool $enabled Whether the exporter is enabled
     */
    public function __construct(bool $enabled = true)
    {
        $this->enabled = $enabled;
        $this->ensureTablesExist();
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
            DB::table('agent_traces')->insert([
                'trace_id' => $traceData['trace_id'],
                'spans_count' => count($traceData['spans']),
                'total_duration' => $traceData['metrics']['total_duration'] ?? 0,
                'tool_calls' => $traceData['metrics']['tool_calls'] ?? 0,
                'handoffs' => $traceData['metrics']['handoffs'] ?? 0,
                'errors' => $traceData['metrics']['errors'] ?? 0,
                'cache_hits' => $traceData['metrics']['cache_hits'] ?? 0,
                'cache_misses' => $traceData['metrics']['cache_misses'] ?? 0,
                'created_at' => now(),
            ]);

            // Export individual spans
            foreach ($traceData['spans'] as $span) {
                $this->exportSpan($span);
            }
        } catch (\Exception $e) {
            \Log::error('DatabaseExporter: Failed to export trace', [
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
            DB::table('agent_spans')->insert([
                'trace_id' => $span['trace_id'],
                'span_id' => $span['id'],
                'name' => $span['name'],
                'parent_span_id' => $span['parent_span_id'] ?? null,
                'start_time' => $span['start_time'],
                'end_time' => $span['end_time'] ?? null,
                'duration' => $span['duration'] ?? 0,
                'attributes' => json_encode($span['attributes'] ?? []),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('DatabaseExporter: Failed to export span', [
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
            DB::table('agent_events')->insert([
                'trace_id' => $event['trace_id'],
                'span_id' => $event['span_id'],
                'event_type' => $event['type'],
                'data' => json_encode($event['data'] ?? []),
                'timestamp' => $event['timestamp'],
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('DatabaseExporter: Failed to export event', [
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
        return 'database';
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Ensure the required tables exist.
     *
     * @return void
     */
    private function ensureTablesExist(): void
    {
        if (!Schema::hasTable('agent_traces')) {
            Schema::create('agent_traces', function ($table) {
                $table->id();
                $table->string('trace_id')->unique();
                $table->integer('spans_count')->default(0);
                $table->float('total_duration')->default(0);
                $table->integer('tool_calls')->default(0);
                $table->integer('handoffs')->default(0);
                $table->integer('errors')->default(0);
                $table->integer('cache_hits')->default(0);
                $table->integer('cache_misses')->default(0);
                $table->timestamps();
                
                $table->index('trace_id');
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('agent_spans')) {
            Schema::create('agent_spans', function ($table) {
                $table->id();
                $table->string('trace_id');
                $table->string('span_id');
                $table->string('name');
                $table->string('parent_span_id')->nullable();
                $table->float('start_time');
                $table->float('end_time')->nullable();
                $table->float('duration')->default(0);
                $table->json('attributes')->nullable();
                $table->timestamps();
                
                $table->index('trace_id');
                $table->index('span_id');
                $table->index('name');
            });
        }

        if (!Schema::hasTable('agent_events')) {
            Schema::create('agent_events', function ($table) {
                $table->id();
                $table->string('trace_id');
                $table->string('span_id');
                $table->string('event_type');
                $table->json('data')->nullable();
                $table->timestamp('timestamp');
                $table->timestamps();
                
                $table->index('trace_id');
                $table->index('span_id');
                $table->index('event_type');
                $table->index('timestamp');
            });
        }
    }
} 