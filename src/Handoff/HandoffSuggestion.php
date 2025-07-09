<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

/**
 * Class HandoffSuggestion
 *
 * Represents a suggested handoff based on context analysis.
 * Contains information about the suggested target agent and reasoning.
 */
class HandoffSuggestion
{
    /**
     * Create a new HandoffSuggestion instance.
     *
     * @param string $targetAgentId The ID of the suggested target agent
     * @param float $confidence The confidence score for this suggestion (0.0 to 1.0)
     * @param string $reason The reason for the handoff suggestion
     * @param int $priority The priority of the handoff (higher values = higher priority)
     * @param array $requiredCapabilities The required capabilities for the target agent
     */
    public function __construct(
        public readonly string $targetAgentId,
        public readonly float $confidence,
        public readonly string $reason,
        public readonly int $priority = 1,
        public readonly array $requiredCapabilities = []
    ) {}

    /**
     * Check if this suggestion has high confidence.
     *
     * @return bool True if confidence is high (>= 0.7)
     */
    public function hasHighConfidence(): bool
    {
        return $this->confidence >= 0.7;
    }

    /**
     * Check if this suggestion has medium confidence.
     *
     * @return bool True if confidence is medium (>= 0.4 and < 0.7)
     */
    public function hasMediumConfidence(): bool
    {
        return $this->confidence >= 0.4 && $this->confidence < 0.7;
    }

    /**
     * Check if this suggestion has low confidence.
     *
     * @return bool True if confidence is low (< 0.4)
     */
    public function hasLowConfidence(): bool
    {
        return $this->confidence < 0.4;
    }

    /**
     * Check if this is a high priority suggestion.
     *
     * @return bool True if priority is high (>= 3)
     */
    public function isHighPriority(): bool
    {
        return $this->priority >= 3;
    }

    /**
     * Get a summary of the suggestion.
     *
     * @return string A summary string
     */
    public function getSummary(): string
    {
        $confidenceLevel = $this->hasHighConfidence() ? 'high' : 
                          ($this->hasMediumConfidence() ? 'medium' : 'low');
        
        $priorityLevel = $this->isHighPriority() ? 'high' : 'normal';
        
        return "Handoff to '{$this->targetAgentId}' with {$confidenceLevel} confidence ({$priorityLevel} priority)";
    }

    /**
     * Convert the suggestion to a HandoffRequest.
     *
     * @param string $sourceAgentId The source agent ID
     * @param string $conversationId The conversation ID
     * @param array $context The context data
     * @return HandoffRequest The handoff request
     */
    public function toHandoffRequest(
        string $sourceAgentId,
        string $conversationId,
        array $context = []
    ): HandoffRequest {
        return new HandoffRequest(
            sourceAgentId: $sourceAgentId,
            targetAgentId: $this->targetAgentId,
            conversationId: $conversationId,
            context: $context,
            metadata: [
                'suggestion_confidence' => $this->confidence,
                'suggestion_reason' => $this->reason,
                'context_analyzed' => true
            ],
            reason: $this->reason,
            priority: $this->priority,
            requiredCapabilities: $this->requiredCapabilities
        );
    }
} 