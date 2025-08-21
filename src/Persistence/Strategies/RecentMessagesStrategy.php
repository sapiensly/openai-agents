<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Persistence\Strategies;

/**
 * Builds a brief context from the last N messages with optional summary.
 */
class RecentMessagesStrategy implements ContextStrategy
{
    private int $maxMessages;
    private int $maxTokens;
    private bool $includeSummary;

    public function __construct()
    {
        // Use unified configuration
        $this->maxMessages = (int) config('sapiensly-openai-agents.persistence.context.max_messages', 20);
        $this->maxTokens = (int) config('sapiensly-openai-agents.persistence.context.max_tokens', 3000);
        $this->includeSummary = config('sapiensly-openai-agents.persistence.context.include_summary', true);
    }


    public function buildContext(array $messages, ?string $summary): ?string
    {
        $maxMessages = $this->maxMessages;
        $includeSummary = $this->includeSummary;

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

    /**
     * Select messages to include in context.
     */
    public function selectMessages(array $messages, array $options = []): array
    {
        // Override defaults with provided options
        $maxMessages = $options['max_messages'] ?? $this->maxMessages;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;
        $includeSummary = $options['include_summary'] ?? $this->includeSummary;

        // If we have fewer messages than the limit, return all
        if (count($messages) <= $maxMessages) {
            return $messages;
        }

        // Take the most recent messages
        $recentMessages = array_slice($messages, -$maxMessages);

        // TODO: Implement token counting and trimming if needed
        // For now, just return the recent messages
        return $recentMessages;
    }

    /**
     * Estimate token count for messages (basic implementation).
     */
    private function estimateTokenCount(array $messages): int
    {
        $totalTokens = 0;

        foreach ($messages as $message) {
            // Simple estimation: ~4 characters per token
            $content = $message['content'] ?? '';
            $totalTokens += (int) ceil(strlen($content) / 4);
        }

        return $totalTokens;
    }

}
