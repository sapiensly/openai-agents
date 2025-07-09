<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tracing;

/**
 * Interface TraceExporterInterface
 *
 * Defines the contract for trace exporters that can send trace data
 * to external systems like Jaeger, Zipkin, or custom monitoring services.
 */
interface TraceExporterInterface
{
    /**
     * Export a complete trace.
     *
     * @param array $traceData The complete trace data
     * @return void
     */
    public function export(array $traceData): void;

    /**
     * Export a single span.
     *
     * @param array $span The span data
     * @return void
     */
    public function exportSpan(array $span): void;

    /**
     * Export a single event.
     *
     * @param array $event The event data
     * @return void
     */
    public function exportEvent(array $event): void;

    /**
     * Get the exporter name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if the exporter is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool;
} 