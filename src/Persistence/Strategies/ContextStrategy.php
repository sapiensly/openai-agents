<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Strategies;

/**
 * Strategy for building compact system context from messages and optional summary.
 */
interface ContextStrategy
{
    /**
     * Build a compact context block string or return null.
     *
     * @param array<int,array> $messages Recent messages
     * @param string|null $summary Optional summary
     * @return string|null Context to inject (e.g., as system message)
     */
    public function buildContext(array $messages, ?string $summary): ?string;
}
