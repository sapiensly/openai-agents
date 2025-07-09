<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Metrics;

use Sapiensly\OpenaiAgents\Handoff\HandoffRequest;
use Sapiensly\OpenaiAgents\Handoff\HandoffResult;

/**
 * Class MetricsCollector
 *
 * Collects and processes metrics related to handoff operations.
 * Metrics can be used for monitoring, debugging, and performance analysis.
 */
class MetricsCollector
{
    /**
     * Whether metrics collection is enabled.
     *
     * @var bool
     */
    private bool $enabled;

    /**
     * Array of metric processors (callables that process metric records).
     *
     * @var array<callable>
     */
    private array $processors;

    /**
     * In-memory storage for collected metrics (for testing and observability).
     *
     * @var array
     */
    private array $collectedMetrics = [];

    /**
     * Create a new MetricsCollector instance.
     *
     * @param array $processors Array of callables that process metric records
     * @param bool $enabled Whether metrics collection is enabled
     */
    public function __construct(
        array $processors = [],
        bool $enabled = true
    ) {
        $this->processors = $processors;
        $this->enabled = $enabled;
    }

    /**
     * Record the start of a handoff operation.
     *
     * @param HandoffRequest $request The handoff request
     * @return void
     */
    public function recordHandoffStart(HandoffRequest $request): void
    {
        if (!$this->enabled) {
            return;
        }

        $record = [
            'event' => 'handoff_start',
            'timestamp' => microtime(true),
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $request->targetAgentId,
            'conversation_id' => $request->conversationId,
            'priority' => $request->priority,
            'required_capabilities' => $request->requiredCapabilities,
        ];

        $this->process($record);
    }

    /**
     * Record a successful handoff operation.
     *
     * @param HandoffRequest $request The handoff request
     * @param HandoffResult $result The handoff result
     * @param float $duration The duration of the handoff operation in seconds
     * @return void
     */
    public function recordHandoffSuccess(
        HandoffRequest $request,
        HandoffResult $result,
        float $duration
    ): void {
        if (!$this->enabled) {
            return;
        }

        $record = [
            'event' => 'handoff_success',
            'timestamp' => microtime(true),
            'handoff_id' => $result->handoffId,
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $result->targetAgentId,
            'conversation_id' => $request->conversationId,
            'duration_ms' => $duration * 1000,
            'context_size' => count($request->context),
        ];

        $this->process($record);
    }

    /**
     * Record a failed handoff operation.
     *
     * @param HandoffRequest $request The handoff request
     * @param \Throwable $error The error that caused the failure
     * @return void
     */
    public function recordHandoffFailure(
        HandoffRequest $request,
        \Throwable $error
    ): void {
        if (!$this->enabled) {
            return;
        }

        $record = [
            'event' => 'handoff_failure',
            'timestamp' => microtime(true),
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $request->targetAgentId,
            'conversation_id' => $request->conversationId,
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
        ];

        $this->process($record);
    }

    /**
     * Record a fallback attempt.
     *
     * @param HandoffRequest $request The handoff request
     * @param \Throwable $error The error that caused the failure
     * @param string $strategy The fallback strategy being used
     * @return void
     */
    public function recordFallbackAttempt(
        HandoffRequest $request,
        \Throwable $error,
        string $strategy
    ): void {
        if (!$this->enabled) {
            return;
        }

        $record = [
            'event' => 'fallback_attempt',
            'timestamp' => microtime(true),
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $request->targetAgentId,
            'conversation_id' => $request->conversationId,
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
            'fallback_strategy' => $strategy,
        ];

        $this->process($record);
    }

    /**
     * Record a handoff suggestion from context analysis.
     *
     * @param HandoffSuggestion $suggestion The handoff suggestion
     * @param string $userInput The user input that triggered the suggestion
     * @return void
     */
    public function recordHandoffSuggestion(
        HandoffSuggestion $suggestion,
        string $userInput
    ): void {
        if (!$this->enabled) {
            return;
        }

        $record = [
            'event' => 'handoff_suggestion',
            'timestamp' => microtime(true),
            'target_agent' => $suggestion->targetAgentId,
            'confidence' => $suggestion->confidence,
            'priority' => $suggestion->priority,
            'reason' => $suggestion->reason,
            'required_capabilities' => $suggestion->requiredCapabilities,
            'user_input_length' => strlen($userInput),
        ];

        $this->process($record);
    }

    /**
     * Record a parallel handoff execution event.
     *
     * @param array $requests
     * @param array $results
     * @param float $duration
     * @return void
     */
    public function recordParallelHandoffExecution(array $requests, array $results, float $duration): void
    {
        $agentCount = count($requests);
        $successCount = 0;
        $failCount = 0;
        foreach ($results as $result) {
            if (($result['status'] ?? 'failed') === 'success') {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        \Log::info("[MetricsCollector] Parallel handoff executed: {$agentCount} agents, {$successCount} success, {$failCount} failed, duration: {$duration}s");
    }

    /**
     * Record a reverse handoff attempt.
     *
     * @param HandoffRequest $request The reverse handoff request
     * @return void
     */
    public function recordReverseHandoffAttempt(HandoffRequest $request): void
    {
        \Log::info("[MetricsCollector] Reverse handoff attempt: {$request->sourceAgentId} → {$request->targetAgentId}");
    }

    /**
     * Process a metric record by passing it to all registered processors and storing in memory.
     *
     * @param array $record The metric record to process
     * @return void
     */
    private function process(array $record): void
    {
        // Store the record in memory for later inspection
        $this->collectedMetrics[] = $record;
        // Call all registered processors
        foreach ($this->processors as $processor) {
            $processor($record);
        }
    }

    /**
     * Add a metric processor.
     *
     * @param callable $processor The processor to add
     * @return void
     */
    public function addProcessor(callable $processor): void
    {
        $this->processors[] = $processor;
    }

    /**
     * Enable or disable metrics collection.
     *
     * @param bool $enabled Whether metrics collection should be enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if metrics collection is enabled.
     *
     * @return bool True if metrics collection is enabled, false otherwise
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get all collected metrics (for testing and observability).
     *
     * @return array
     */
    public function getCollectedMetrics(): array
    {
        return $this->collectedMetrics;
    }
}
