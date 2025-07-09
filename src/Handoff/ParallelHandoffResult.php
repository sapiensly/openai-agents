<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

/**
 * Class ParallelHandoffResult
 *
 * Represents the result of parallel handoff operations, including
 * individual agent results, timing information, and merged responses.
 */
class ParallelHandoffResult
{
    /**
     * Create a new ParallelHandoffResult instance.
     *
     * @param array $results Array of individual agent results
     * @param string $status The overall status
     * @param float|null $duration The total duration in seconds
     * @param string|null $mergedResponse The merged response from all agents
     */
    public function __construct(
        private array $results,
        private string $status,
        private ?float $duration = null,
        private ?string $mergedResponse = null
    ) {}

    /**
     * Get the individual agent results.
     *
     * @return array The agent results
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get the overall status.
     *
     * @return string The status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get the total duration.
     *
     * @return float|null The duration in seconds
     */
    public function getDuration(): ?float
    {
        return $this->duration;
    }

    /**
     * Get the merged response.
     *
     * @return string|null The merged response
     */
    public function getMergedResponse(): ?string
    {
        return $this->mergedResponse;
    }

    /**
     * Set the merged response.
     *
     * @param string $mergedResponse The merged response
     * @return void
     */
    public function setMergedResponse(string $mergedResponse): void
    {
        $this->mergedResponse = $mergedResponse;
    }

    /**
     * Check if the parallel handoff was successful.
     *
     * @return bool True if successful
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Get successful results only.
     *
     * @return array Array of successful results
     */
    public function getSuccessfulResults(): array
    {
        return array_filter($this->results, function ($result) {
            return ($result['status'] ?? 'failed') === 'success';
        });
    }

    /**
     * Get failed results only.
     *
     * @return array Array of failed results
     */
    public function getFailedResults(): array
    {
        return array_filter($this->results, function ($result) {
            return ($result['status'] ?? 'success') === 'failed';
        });
    }

    /**
     * Get the number of successful agents.
     *
     * @return int The number of successful agents
     */
    public function getSuccessfulAgentCount(): int
    {
        return count($this->getSuccessfulResults());
    }

    /**
     * Get the number of failed agents.
     *
     * @return int The number of failed agents
     */
    public function getFailedAgentCount(): int
    {
        return count($this->getFailedResults());
    }

    /**
     * Get the average response time.
     *
     * @return float|null The average response time in seconds
     */
    public function getAverageResponseTime(): ?float
    {
        $successfulResults = $this->getSuccessfulResults();
        
        if (empty($successfulResults)) {
            return null;
        }

        $totalTime = 0;
        foreach ($successfulResults as $result) {
            $totalTime += $result['duration'] ?? 0;
        }

        return $totalTime / count($successfulResults);
    }

    /**
     * Get the fastest response time.
     *
     * @return float|null The fastest response time in seconds
     */
    public function getFastestResponseTime(): ?float
    {
        $successfulResults = $this->getSuccessfulResults();
        
        if (empty($successfulResults)) {
            return null;
        }

        $times = array_column($successfulResults, 'duration');
        return min($times);
    }

    /**
     * Get the slowest response time.
     *
     * @return float|null The slowest response time in seconds
     */
    public function getSlowestResponseTime(): ?float
    {
        $successfulResults = $this->getSuccessfulResults();
        
        if (empty($successfulResults)) {
            return null;
        }

        $times = array_column($successfulResults, 'duration');
        return max($times);
    }

    /**
     * Get a summary of the parallel handoff results.
     *
     * @return array The summary
     */
    public function getSummary(): array
    {
        return [
            'status' => $this->status,
            'total_agents' => count($this->results),
            'successful_agents' => $this->getSuccessfulAgentCount(),
            'failed_agents' => $this->getFailedAgentCount(),
            'total_duration' => $this->duration,
            'average_response_time' => $this->getAverageResponseTime(),
            'fastest_response_time' => $this->getFastestResponseTime(),
            'slowest_response_time' => $this->getSlowestResponseTime(),
            'success_rate' => count($this->results) > 0 ? 
                ($this->getSuccessfulAgentCount() / count($this->results)) * 100 : 0
        ];
    }

    /**
     * Convert to array representation.
     *
     * @return array The array representation
     */
    public function toArray(): array
    {
        return [
            'results' => $this->results,
            'status' => $this->status,
            'duration' => $this->duration,
            'merged_response' => $this->mergedResponse,
            'summary' => $this->getSummary()
        ];
    }
} 