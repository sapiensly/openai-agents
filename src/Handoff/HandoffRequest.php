<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

/**
 * Class HandoffRequest
 *
 * Represents a request to transfer control from one agent to another.
 * Contains all the necessary information for the handoff process.
 */
class HandoffRequest
{
    /**
     * Create a new HandoffRequest instance.
     *
     * @param string $sourceAgentId The ID of the agent initiating the handoff
     * @param string $targetAgentId The ID of the target agent
     * @param string $conversationId The ID of the conversation
     * @param array $context Additional context data to pass to the target agent
     * @param array $metadata Additional metadata about the handoff
     * @param string|null $reason The reason for the handoff
     * @param int $priority The priority of the handoff (higher values indicate higher priority)
     * @param array $requiredCapabilities Capabilities required by the target agent
     * @param string|null $fallbackAgentId ID of the agent to use if the target agent is unavailable
     */
    public function __construct(
        public readonly string $sourceAgentId,
        public readonly string $targetAgentId,
        public readonly string $conversationId,
        public readonly array $context = [],
        public readonly array $metadata = [],
        public readonly ?string $reason = null,
        public readonly int $priority = 1,
        public readonly array $requiredCapabilities = [],
        public readonly ?string $fallbackAgentId = null
    ) {}
}
