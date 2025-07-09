<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Metrics;

use Sapiensly\OpenaiAgents\Handoff\HandoffRequest;
use Sapiensly\OpenaiAgents\Handoff\HandoffResult;
use Sapiensly\OpenaiAgents\Handoff\HandoffSuggestion;

/**
 * Class AdvancedMetricsCollector
 *
 * Advanced metrics collector that provides detailed analytics and insights
 * for handoff operations. Extends the basic MetricsCollector with additional
 * functionality.
 */
class AdvancedMetricsCollector extends MetricsCollector
{
    /**
     * Array to store metrics data for analysis.
     *
     * @var array
     */
    private array $metricsData = [];

    /**
     * Create a new AdvancedMetricsCollector instance.
     *
     * @param array $processors Array of callables that process metric records
     * @param bool $enabled Whether metrics collection is enabled
     */
    public function __construct(array $processors = [], bool $enabled = true)
    {
        parent::__construct($processors, $enabled);
    }

    /**
     * Record a handoff attempt with detailed analytics.
     *
     * @param HandoffRequest $request The handoff request
     * @return void
     */
    public function recordHandoffAttempt(HandoffRequest $request): void
    {
        $this->record('handoff_attempt', [
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $request->targetAgentId,
            'reason' => $request->reason,
            'priority' => $request->priority,
            'required_capabilities' => $request->requiredCapabilities,
            'timestamp' => microtime(true),
            'conversation_id' => $request->conversationId,
            'context_size' => count($request->context),
            'has_fallback' => !empty($request->fallbackAgentId)
        ]);
    }

    /**
     * Record a successful handoff with performance metrics.
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
        $this->record('handoff_success', [
            'handoff_id' => $result->handoffId,
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $result->targetAgentId,
            'conversation_id' => $request->conversationId,
            'duration_ms' => $duration * 1000,
            'context_size' => count($request->context),
            'timestamp' => microtime(true),
            'priority' => $request->priority,
            'required_capabilities' => $request->requiredCapabilities
        ]);
    }

    /**
     * Record a failed handoff with error analysis.
     *
     * @param HandoffRequest $request The handoff request
     * @param \Throwable $error The error that caused the failure
     * @return void
     */
    public function recordHandoffFailure(
        HandoffRequest $request,
        \Throwable $error
    ): void {
        $this->record('handoff_failure', [
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $request->targetAgentId,
            'conversation_id' => $request->conversationId,
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
            'timestamp' => microtime(true),
            'priority' => $request->priority,
            'required_capabilities' => $request->requiredCapabilities
        ]);
    }

    /**
     * Record a fallback attempt with strategy information.
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
        $this->record('fallback_attempt', [
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $request->targetAgentId,
            'conversation_id' => $request->conversationId,
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
            'fallback_strategy' => $strategy,
            'timestamp' => microtime(true),
            'original_target' => $request->targetAgentId
        ]);
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
        $this->record('handoff_suggestion', [
            'target_agent' => $suggestion->targetAgentId,
            'confidence' => $suggestion->confidence,
            'priority' => $suggestion->priority,
            'reason' => $suggestion->reason,
            'required_capabilities' => $suggestion->requiredCapabilities,
            'user_input_length' => strlen($userInput),
            'timestamp' => microtime(true),
            'confidence_level' => $this->getConfidenceLevel($suggestion->confidence)
        ]);
    }

    /**
     * Get comprehensive handoff analytics.
     *
     * @return array The analytics data
     */
    public function getHandoffAnalytics(): array
    {
        $data = $this->getMetricsData();
        
        return [
            'success_rate' => $this->calculateSuccessRate($data),
            'average_duration' => $this->calculateAverageDuration($data),
            'most_common_targets' => $this->getMostCommonTargets($data),
            'failure_patterns' => $this->getFailurePatterns($data),
            'suggestion_accuracy' => $this->calculateSuggestionAccuracy($data),
            'performance_metrics' => $this->getPerformanceMetrics($data),
            'error_analysis' => $this->getErrorAnalysis($data),
            'capability_usage' => $this->getCapabilityUsage($data)
        ];
    }

    /**
     * Calculate handoff success rate.
     *
     * @param array $data The metrics data
     * @return float The success rate (0.0 to 1.0)
     */
    private function calculateSuccessRate(array $data): float
    {
        $successes = count(array_filter($data, fn($record) => $record['event'] === 'handoff_success'));
        $failures = count(array_filter($data, fn($record) => $record['event'] === 'handoff_failure'));
        
        $total = $successes + $failures;
        
        return $total > 0 ? $successes / $total : 0.0;
    }

    /**
     * Calculate average handoff duration.
     *
     * @param array $data The metrics data
     * @return float The average duration in milliseconds
     */
    private function calculateAverageDuration(array $data): float
    {
        $successRecords = array_filter($data, fn($record) => $record['event'] === 'handoff_success');
        
        if (empty($successRecords)) {
            return 0.0;
        }
        
        $totalDuration = array_sum(array_column($successRecords, 'duration_ms'));
        return $totalDuration / count($successRecords);
    }

    /**
     * Get most common target agents.
     *
     * @param array $data The metrics data
     * @return array Array of target agents with counts
     */
    private function getMostCommonTargets(array $data): array
    {
        $targets = [];
        
        foreach ($data as $record) {
            if (isset($record['target_agent'])) {
                $target = $record['target_agent'];
                $targets[$target] = ($targets[$target] ?? 0) + 1;
            }
        }
        
        arsort($targets);
        return $targets;
    }

    /**
     * Get failure patterns analysis.
     *
     * @param array $data The metrics data
     * @return array The failure patterns
     */
    private function getFailurePatterns(array $data): array
    {
        $failures = array_filter($data, fn($record) => $record['event'] === 'handoff_failure');
        
        $patterns = [
            'error_types' => [],
            'source_target_pairs' => [],
            'common_errors' => []
        ];
        
        foreach ($failures as $failure) {
            // Error types
            $errorClass = $failure['error_class'] ?? 'unknown';
            $patterns['error_types'][$errorClass] = ($patterns['error_types'][$errorClass] ?? 0) + 1;
            
            // Source-target pairs
            $pair = $failure['source_agent'] . ' -> ' . $failure['target_agent'];
            $patterns['source_target_pairs'][$pair] = ($patterns['source_target_pairs'][$pair] ?? 0) + 1;
            
            // Common errors
            $error = $failure['error'] ?? 'unknown';
            $patterns['common_errors'][$error] = ($patterns['common_errors'][$error] ?? 0) + 1;
        }
        
        return $patterns;
    }

    /**
     * Calculate suggestion accuracy.
     *
     * @param array $data The metrics data
     * @return float The accuracy rate (0.0 to 1.0)
     */
    private function calculateSuggestionAccuracy(array $data): float
    {
        $suggestions = array_filter($data, fn($record) => $record['event'] === 'handoff_suggestion');
        $successes = array_filter($data, fn($record) => $record['event'] === 'handoff_success');
        
        // This is a simplified calculation - in a real implementation,
        // you'd need to correlate suggestions with their outcomes
        return count($suggestions) > 0 ? count($successes) / count($suggestions) : 0.0;
    }

    /**
     * Get performance metrics.
     *
     * @param array $data The metrics data
     * @return array The performance metrics
     */
    private function getPerformanceMetrics(array $data): array
    {
        $successRecords = array_filter($data, fn($record) => $record['event'] === 'handoff_success');
        
        if (empty($successRecords)) {
            return [
                'min_duration' => 0,
                'max_duration' => 0,
                'median_duration' => 0,
                'p95_duration' => 0
            ];
        }
        
        $durations = array_column($successRecords, 'duration_ms');
        sort($durations);
        
        return [
            'min_duration' => min($durations),
            'max_duration' => max($durations),
            'median_duration' => $durations[count($durations) / 2] ?? 0,
            'p95_duration' => $durations[(int)(count($durations) * 0.95)] ?? 0
        ];
    }

    /**
     * Get error analysis.
     *
     * @param array $data The metrics data
     * @return array The error analysis
     */
    private function getErrorAnalysis(array $data): array
    {
        $failures = array_filter($data, fn($record) => $record['event'] === 'handoff_failure');
        
        $analysis = [
            'total_errors' => count($failures),
            'error_distribution' => [],
            'recovery_rate' => 0.0
        ];
        
        foreach ($failures as $failure) {
            $errorClass = $failure['error_class'] ?? 'unknown';
            $analysis['error_distribution'][$errorClass] = ($analysis['error_distribution'][$errorClass] ?? 0) + 1;
        }
        
        // Calculate recovery rate (fallback attempts vs total failures)
        $fallbacks = count(array_filter($data, fn($record) => $record['event'] === 'fallback_attempt'));
        $analysis['recovery_rate'] = count($failures) > 0 ? $fallbacks / count($failures) : 0.0;
        
        return $analysis;
    }

    /**
     * Get capability usage statistics.
     *
     * @param array $data The metrics data
     * @return array The capability usage
     */
    private function getCapabilityUsage(array $data): array
    {
        $capabilities = [];
        
        foreach ($data as $record) {
            if (isset($record['required_capabilities']) && is_array($record['required_capabilities'])) {
                foreach ($record['required_capabilities'] as $capability) {
                    $capabilities[$capability] = ($capabilities[$capability] ?? 0) + 1;
                }
            }
        }
        
        arsort($capabilities);
        return $capabilities;
    }

    /**
     * Get confidence level string.
     *
     * @param float $confidence The confidence score
     * @return string The confidence level
     */
    private function getConfidenceLevel(float $confidence): string
    {
        if ($confidence >= 0.8) {
            return 'high';
        } elseif ($confidence >= 0.5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get all metrics data.
     *
     * @return array The metrics data
     */
    private function getMetricsData(): array
    {
        return $this->metricsData;
    }

    /**
     * Override the process method to store data for analysis.
     *
     * @param array $record The metric record to process
     * @return void
     */
    protected function process(array $record): void
    {
        // Store the record for analysis
        $this->metricsData[] = $record;
        
        // Call parent process method
        parent::process($record);
    }

    /**
     * Clear all metrics data.
     *
     * @return void
     */
    public function clearMetrics(): void
    {
        $this->metricsData = [];
    }

    /**
     * Export metrics data to JSON.
     *
     * @return string The JSON representation of metrics data
     */
    public function exportMetrics(): string
    {
        return json_encode($this->metricsData, JSON_PRETTY_PRINT);
    }
} 