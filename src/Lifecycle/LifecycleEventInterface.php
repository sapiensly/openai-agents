<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Lifecycle;

/**
 * Interface LifecycleEventInterface
 *
 * Defines the contract for lifecycle events in the agent system.
 */
interface LifecycleEventInterface
{
    /**
     * Get the event type.
     *
     * @return string The event type
     */
    public function getType(): string;

    /**
     * Get the event data.
     *
     * @return array The event data
     */
    public function getData(): array;

    /**
     * Get the event timestamp.
     *
     * @return int The event timestamp
     */
    public function getTimestamp(): int;

    /**
     * Get the agent ID associated with this event.
     *
     * @return string|null The agent ID or null
     */
    public function getAgentId(): ?string;
} 