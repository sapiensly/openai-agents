<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

/**
 * Class HandoffResult
 *
 * Represents the result of a handoff operation.
 * Contains information about the success or failure of the handoff.
 */
class HandoffResult
{
    /**
     * Create a new HandoffResult instance.
     *
     * @param string $handoffId Unique identifier for this handoff operation
     * @param string $status Status of the handoff ('success', 'failed', 'pending')
     * @param string|null $targetAgentId The ID of the target agent
     * @param string|null $errorMessage Error message if the handoff failed
     * @param array $context Context data passed to the target agent
     */
    public function __construct(
        public readonly string $handoffId,
        public readonly string $status,
        public readonly ?string $targetAgentId,
        public readonly ?string $errorMessage = null,
        public readonly array $context = []
    ) {}

    /**
     * Check if the handoff was successful.
     *
     * @return bool True if the handoff was successful, false otherwise
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the handoff failed.
     *
     * @return bool True if the handoff failed, false otherwise
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the handoff is pending.
     *
     * @return bool True if the handoff is pending, false otherwise
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
