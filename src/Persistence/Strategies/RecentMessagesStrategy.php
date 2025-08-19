<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Strategies;

/**
 * Builds a brief context from the last N messages with optional summary.
 */
class RecentMessagesStrategy implements ContextStrategy
{
    public function buildContext(array $messages, ?string $summary): ?string
    {
        $maxMessages = (int) (config('agent-persistence.context.max_messages') ?? 20);
        $includeSummary = (bool) (config('agent-persistence.context.include_summary') ?? true);

        $recent = array_slice($messages, -$maxMessages);
        $parts = [];
        if ($includeSummary && $summary) {
            $parts[] = "Summary so far:\n" . trim($summary);
        }
        if (!empty($recent)) {
            $parts[] = "Recent messages:\n" . implode("\n", array_map(function ($m) {
                $role = strtoupper((string)($m['role'] ?? ''));
                $content = (string)($m['content'] ?? '');
                return "- {$role}: " . substr($content, 0, 500);
            }, $recent));
        }
        $context = trim(implode("\n\n", $parts));
        return $context !== '' ? $context : null;
    }
}
