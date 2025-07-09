<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Lifecycle;

/**
 * Class LifecycleEvent
 *
 * Represents a lifecycle event in the agent system.
 */
class LifecycleEvent implements LifecycleEventInterface
{
    /**
     * Create a new LifecycleEvent instance.
     *
     * @param string $type The event type
     * @param array $data The event data
     * @param int|null $timestamp The event timestamp (defaults to current time)
     */
    public function __construct(
        private string $type,
        private array $data,
        private ?int $timestamp = null
    ) {
        $this->timestamp ??= time();
    }

    /**
     * Get the event type.
     *
     * @return string The event type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the event data.
     *
     * @return array The event data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the event timestamp.
     *
     * @return int The event timestamp
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Get the agent ID associated with this event.
     *
     * @return string|null The agent ID or null
     */
    public function getAgentId(): ?string
    {
        return $this->data['agent_id'] ?? null;
    }

    /**
     * Convert the event to an array.
     *
     * @return array The event as an array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
            'agent_id' => $this->getAgentId(),
        ];
    }
} 