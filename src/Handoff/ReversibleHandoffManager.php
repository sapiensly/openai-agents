<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Sapiensly\OpenaiAgents\State\ConversationStateManager;
use Sapiensly\OpenaiAgents\Metrics\MetricsCollector;
use Illuminate\Support\Facades\Log;

/**
 * Class ReversibleHandoffManager
 *
 * Manages reversible handoffs, allowing agents to return to previous agents
 * in the conversation flow. Maintains a stack of agent transitions.
 */
class ReversibleHandoffManager
{
    /**
     * Create a new ReversibleHandoffManager instance.
     *
     * @param ConversationStateManager $stateManager The conversation state manager
     * @param MetricsCollector $metrics The metrics collector
     */
    public function __construct(
        private ConversationStateManager $stateManager,
        private MetricsCollector $metrics
    ) {}

    /**
     * Check if a handoff can be reversed.
     *
     * @param string $conversationId The conversation ID
     * @return bool True if the handoff can be reversed
     */
    public function canReverseHandoff(string $conversationId): bool
    {
        $handoffHistory = $this->stateManager->getHandoffHistory($conversationId);
        return count($handoffHistory) > 1; // Need at least 2 handoffs to reverse
    }

    /**
     * Get the previous agent in the conversation.
     *
     * @param string $conversationId The conversation ID
     * @return string|null The previous agent ID, or null if none available
     */
    public function getPreviousAgent(string $conversationId): ?string
    {
        $handoffHistory = $this->stateManager->getHandoffHistory($conversationId);
        
        if (count($handoffHistory) < 2) {
            return null;
        }

        // Get the second-to-last handoff's source agent
        $secondToLast = $handoffHistory[count($handoffHistory) - 2];
        return $secondToLast['source_agent'] ?? null;
    }

    /**
     * Get the handoff stack for a conversation.
     *
     * @param string $conversationId The conversation ID
     * @return array The handoff stack
     */
    public function getHandoffStack(string $conversationId): array
    {
        $handoffHistory = $this->stateManager->getHandoffHistory($conversationId);
        $stack = [];

        foreach ($handoffHistory as $handoff) {
            $stack[] = [
                'from' => $handoff['source_agent'],
                'to' => $handoff['target_agent'],
                'timestamp' => $handoff['timestamp'],
                'handoff_id' => $handoff['handoff_id'] ?? null
            ];
        }

        return $stack;
    }

    /**
     * Reverse the last handoff.
     *
     * @param string $conversationId The conversation ID
     * @param string $currentAgentId The current agent ID
     * @param array $context The current context
     * @return HandoffResult|null The result of the reverse handoff, or null if not possible
     */
    public function reverseLastHandoff(string $conversationId, string $currentAgentId, array $context = []): ?HandoffResult
    {
        if (!$this->canReverseHandoff($conversationId)) {
            Log::warning("[ReversibleHandoffManager] Cannot reverse handoff - insufficient history");
            return null;
        }

        $previousAgent = $this->getPreviousAgent($conversationId);
        
        if (!$previousAgent) {
            Log::warning("[ReversibleHandoffManager] No previous agent found");
            return null;
        }

        // Create reverse handoff request
        $request = new HandoffRequest(
            sourceAgentId: $currentAgentId,
            targetAgentId: $previousAgent,
            conversationId: $conversationId,
            context: $context,
            metadata: [
                'reverse_handoff' => true,
                'original_agent' => $currentAgentId,
                'reason' => 'User requested to return to previous agent'
            ],
            reason: "Reversing handoff to return to previous agent",
            priority: 2, // Higher priority for user-requested reversals
            requiredCapabilities: [],
            fallbackAgentId: null
        );

        // Record the reverse handoff attempt
        $this->metrics->recordReverseHandoffAttempt($request);

        Log::info("[ReversibleHandoffManager] Reversing handoff from {$currentAgentId} to {$previousAgent}");

        return new HandoffResult(
            handoffId: $this->generateHandoffId(),
            status: 'success',
            targetAgentId: $previousAgent,
            context: $context
        );
    }

    /**
     * Check if a handoff should be automatically reversible.
     *
     * @param HandoffRequest $request The handoff request
     * @return bool True if the handoff should be reversible
     */
    public function shouldBeReversible(HandoffRequest $request): bool
    {
        // Check if this is a temporary handoff (e.g., for specialized task)
        $temporaryKeywords = ['temporary', 'quick', 'brief', 'assist', 'help'];
        $reason = strtolower($request->reason ?? '');
        
        foreach ($temporaryKeywords as $keyword) {
            if (str_contains($reason, $keyword)) {
                return true;
            }
        }

        // Check if this is a low-confidence handoff
        if (isset($request->metadata['suggestion_confidence'])) {
            $confidence = $request->metadata['suggestion_confidence'];
            if ($confidence < 0.6) {
                return true; // Low confidence handoffs should be reversible
            }
        }

        return false;
    }

    /**
     * Get the optimal agent to return to.
     *
     * @param string $conversationId The conversation ID
     * @param string $currentAgentId The current agent ID
     * @param array $userIntent The user's current intent
     * @return string|null The optimal agent to return to, or null if none found
     */
    public function getOptimalReturnAgent(string $conversationId, string $currentAgentId, array $userIntent = []): ?string
    {
        $handoffStack = $this->getHandoffStack($conversationId);
        
        if (empty($handoffStack)) {
            return null;
        }

        // If user intent is specified, find the best matching agent from history
        if (!empty($userIntent)) {
            foreach (array_reverse($handoffStack) as $handoff) {
                $agentId = $handoff['from'];
                if ($this->agentMatchesIntent($agentId, $userIntent)) {
                    return $agentId;
                }
            }
        }

        // Default: return to the previous agent
        return $this->getPreviousAgent($conversationId);
    }

    /**
     * Check if an agent matches the user intent.
     *
     * @param string $agentId The agent ID
     * @param array $userIntent The user intent
     * @return bool True if the agent matches the intent
     */
    private function agentMatchesIntent(string $agentId, array $userIntent): bool
    {
        $domain = $userIntent['primary_domain'] ?? 'general';
        
        $agentDomainMap = [
            'mathematics' => 'math_agent',
            'history' => 'history_agent',
            'technical_support' => 'technical_support',
            'sales' => 'sales_agent'
        ];

        return $agentDomainMap[$domain] === $agentId;
    }

    /**
     * Get handoff statistics for a conversation.
     *
     * @param string $conversationId The conversation ID
     * @return array The handoff statistics
     */
    public function getHandoffStats(string $conversationId): array
    {
        $handoffStack = $this->getHandoffStack($conversationId);
        
        $stats = [
            'total_handoffs' => count($handoffStack),
            'reversible_handoffs' => 0,
            'agent_visits' => [],
            'handoff_pattern' => []
        ];

        foreach ($handoffStack as $handoff) {
            // Count agent visits
            $from = $handoff['from'];
            $to = $handoff['to'];
            
            $stats['agent_visits'][$from] = ($stats['agent_visits'][$from] ?? 0) + 1;
            $stats['agent_visits'][$to] = ($stats['agent_visits'][$to] ?? 0) + 1;
            
            // Record handoff pattern
            $stats['handoff_pattern'][] = "{$from} -> {$to}";
            
            // Check if this handoff was reversible
            if ($this->shouldBeReversible(new HandoffRequest(
                sourceAgentId: $from,
                targetAgentId: $to,
                conversationId: $conversationId,
                reason: "Analysis of handoff pattern"
            ))) {
                $stats['reversible_handoffs']++;
            }
        }

        return $stats;
    }

    /**
     * Generate a unique handoff ID.
     *
     * @return string The generated handoff ID
     */
    private function generateHandoffId(): string
    {
        return 'ho_' . uniqid('', true);
    }
} 