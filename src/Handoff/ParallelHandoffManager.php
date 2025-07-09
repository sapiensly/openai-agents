<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Sapiensly\OpenaiAgents\Registry\AgentRegistry;
use Sapiensly\OpenaiAgents\State\ConversationStateManager;
use Sapiensly\OpenaiAgents\Metrics\MetricsCollector;
use Illuminate\Support\Facades\Log;

/**
 * Class ParallelHandoffManager
 *
 * Manages parallel handoffs where multiple agents work simultaneously
 * on different aspects of a user request. Coordinates responses and
 * merges results from multiple agents.
 */
class ParallelHandoffManager
{
    /**
     * Create a new ParallelHandoffManager instance.
     *
     * @param AgentRegistry $registry The agent registry
     * @param ConversationStateManager $stateManager The conversation state manager
     * @param MetricsCollector $metrics The metrics collector
     */
    public function __construct(
        private AgentRegistry $registry,
        private ConversationStateManager $stateManager,
        private MetricsCollector $metrics,
        private ?\Sapiensly\OpenaiAgents\Handoff\IntelligentCacheManager $cacheManager = null
    ) {}

    public function setCacheManager($cacheManager) {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Analyze user input to determine if parallel handoffs are needed.
     *
     * @param string $userInput The user's input
     * @return array Array of parallel handoff requests
     */
    public function analyzeParallelHandoffs(string $userInput): array
    {
        $parallelRequests = [];
        $input = strtolower($userInput);

        // Check for multiple domains in the same request
        $domains = $this->extractMultipleDomains($input);
        
        if (count($domains) > 1) {
            foreach ($domains as $domain) {
                $agents = $this->findAgentsForDomain($domain);
                foreach ($agents as $agent) {
                    $parallelRequests[] = $this->createParallelRequest($agent, $userInput, $domain);
                }
            }
        }

        return $parallelRequests;
    }

    /**
     * Execute parallel handoffs.
     *
     * @param array $requests Array of parallel handoff requests
     * @param string $conversationId The conversation ID
     * @return ParallelHandoffResult The result of parallel handoffs
     */
    public function executeParallelHandoffs(array $requests, string $conversationId): ParallelHandoffResult
    {
        if (empty($requests)) {
            return new ParallelHandoffResult([], 'no_parallel_handoffs_needed');
        }

        Log::info("[ParallelHandoffManager] Executing " . count($requests) . " parallel handoffs");

        $results = [];
        $startTime = microtime(true);

        // Execute handoffs in parallel (simulated with sequential execution for now)
        foreach ($requests as $request) {
            $result = $this->executeSingleParallelHandoff($request, $conversationId);
            $results[] = $result;
        }

        $duration = microtime(true) - $startTime;

        // Record parallel handoff metrics
        $this->metrics->recordParallelHandoffExecution($requests, $results, $duration);

        return new ParallelHandoffResult($results, 'success', $duration);
    }

    /**
     * Merge responses from parallel agents.
     *
     * @param array $responses Array of agent responses
     * @param string $originalQuery The original user query
     * @return string The merged response
     */
    public function mergeParallelResponses(array $responses, string $originalQuery): string
    {
        if (empty($responses)) {
            return "Could not process your request with multiple agents.";
        }

        if (count($responses) === 1) {
            $r = $responses[0];
            $agentName = $r['agent_name'] ?? $r['agent_id'] ?? 'Agent';
            $resp = trim($r['response'] ?? 'No response.');
            return "Response from {$agentName}:\n{$resp}";
        }

        $mergedResponse = "\nSummary of responses from specialized agents:\n";
        foreach ($responses as $response) {
            $agentName = $response['agent_name'] ?? $response['agent_id'] ?? 'Agent';
            $resp = trim($response['response'] ?? 'No response.');
            $mergedResponse .= "\n🧑‍💼 <b>{$agentName}</b>:\n";
            $mergedResponse .= $resp !== '' ? $resp : 'No response.';
            $mergedResponse .= "\n";
        }
        $mergedResponse .= "\n---\n*This response combines information from multiple agents.*";
        return $mergedResponse;
    }

    /**
     * Extract multiple domains from user input.
     *
     * @param string $input The user input
     * @return array Array of detected domains
     */
    private function extractMultipleDomains(string $input): array
    {
        $domains = [];

        // Math domain
        if ($this->containsMathContent($input)) {
            $domains[] = 'mathematics';
        }

        // History domain
        if ($this->containsHistoryContent($input)) {
            $domains[] = 'history';
        }

        // Technical support domain
        if ($this->containsTechnicalContent($input)) {
            $domains[] = 'technical_support';
        }

        // Sales domain
        if ($this->containsSalesContent($input)) {
            $domains[] = 'sales';
        }

        return array_unique($domains);
    }

    /**
     * Check if input contains math content.
     *
     * @param string $input The input to check
     * @return bool True if math content is detected
     */
    private function containsMathContent(string $input): bool
    {
        $mathPatterns = [
            '/\b(?:calculate|solve|compute|add|subtract|multiply|divide|sum|total|equation|formula|math|matemáticas|matematica|cálculos|calculo)\b/',
            '/\b\d+\s*[\+\-\*\/]\s*\d+\b/',
            '/\b(?:what is|how much is|qué es|cuánto es)\s+\d+/',
            '/\b(?:cálculos matemáticos|matemáticas|math|calculations)\b/'
        ];

        foreach ($mathPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if input contains history content.
     *
     * @param string $input The input to check
     * @return bool True if history content is detected
     */
    private function containsHistoryContent(string $input): bool
    {
        $historyPatterns = [
            '/\b(?:history|historical|ancient|medieval|war|battle|empire|kingdom|dynasty|historia|histórico|histórica)\b/',
            '/\b(?:who was|what happened|when did|tell me about|quién fue|qué pasó|cuándo|dime sobre)\b/',
            '/\b(?:egypt|rome|greece|china|india|world war|civil war|egipto|roma|grecia|china|india|guerra mundial|guerra civil)\b/',
            '/\b(?:historia de|history of|información sobre|information about)\b/'
        ];

        foreach ($historyPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if input contains technical content.
     *
     * @param string $input The input to check
     * @return bool True if technical content is detected
     */
    private function containsTechnicalContent(string $input): bool
    {
        $technicalPatterns = [
            '/\b(?:technical|support|help|troubleshoot|error|bug|problem|fix|repair)\b/',
            '/\b(?:computer|laptop|desktop|software|hardware|system|network)\b/',
            '/\b(?:doesn\'t work|not working|broken|crashed|frozen|slow)\b/'
        ];

        foreach ($technicalPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if input contains sales content.
     *
     * @param string $input The input to check
     * @return bool True if sales content is detected
     */
    private function containsSalesContent(string $input): bool
    {
        $salesPatterns = [
            '/\b(?:buy|purchase|order|price|cost|discount|sale|deal|offer)\b/',
            '/\b(?:product|service|item|package|plan|tier|premium)\b/',
            '/\b(?:how much|what\'s the price|cost|pricing|rates)\b/'
        ];

        foreach ($salesPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find agents for a specific domain.
     *
     * @param string $domain The domain to find agents for
     * @return array Array of agent IDs
     */
    private function findAgentsForDomain(string $domain): array
    {
        $domainAgentMap = [
            'mathematics' => ['math_agent'],
            'history' => ['history_agent'],
            'technical_support' => ['technical_support'],
            'sales' => ['sales_agent']
        ];

        return $domainAgentMap[$domain] ?? [];
    }

    /**
     * Create a parallel handoff request.
     *
     * @param string $agentId The agent ID
     * @param string $userInput The user input
     * @param string $domain The domain
     * @return HandoffRequest The handoff request
     */
    private function createParallelRequest(string $agentId, string $userInput, string $domain): HandoffRequest
    {
        return new HandoffRequest(
            sourceAgentId: 'parallel_coordinator',
            targetAgentId: $agentId,
            conversationId: 'parallel_' . uniqid(),
            context: [
                'user_input' => $userInput,
                'domain' => $domain,
                'parallel_execution' => true
            ],
            metadata: [
                'parallel_handoff' => true,
                'domain' => $domain,
                'original_query' => $userInput
            ],
            reason: "Parallel handoff for {$domain} domain",
            priority: 1,
            requiredCapabilities: [$domain],
            fallbackAgentId: null
        );
    }

    /**
     * Execute a single parallel handoff.
     *
     * @param HandoffRequest $request The handoff request
     * @param string $conversationId The conversation ID
     * @return array The execution result
     */
    private function executeSingleParallelHandoff(HandoffRequest $request, string $conversationId, bool $cacheDebug = false): array
    {
        $startTime = microtime(true);
        $agentId = $request->targetAgentId;
        $userInput = $request->context['user_input'] ?? '';
        $context = $request->context;
        $response = null;
        $cacheHit = false;
        try {
            $agent = $this->registry->getAgent($agentId);
            if (!$agent) {
                return [
                    'agent_id' => $agentId,
                    'status' => 'failed',
                    'error' => 'Agent not found',
                    'duration' => microtime(true) - $startTime
                ];
            }
            // Search in cache before calling the agent
            if ($this->cacheManager) {
                $cached = $this->cacheManager->getCachedAgentResponse($agentId, $userInput, $context);
                if ($cached) {
                    $response = $cached;
                    $cacheHit = true;
                    if ($cacheDebug) {
                        Log::info("[ParallelHandoffManager] Cache HIT for response from {$agentId}");
                    }
                }
            }
            if (!$response) {
                try {
                    $response = $agent->chat($userInput);
                } catch (\Throwable $e) {
                    $response = $this->simulateAgentResponse($agent, $userInput);
                }
                // Cache the response
                if ($this->cacheManager) {
                    $this->cacheManager->cacheAgentResponse($agentId, $userInput, $context, $response);
                    if ($cacheDebug) {
                        Log::info("[ParallelHandoffManager] Cache MISS for response from {$agentId}, caching result.");
                    }
                }
            }
            return [
                'agent_id' => $agentId,
                'agent_name' => $this->getAgentName($agentId),
                'status' => 'success',
                'response' => $response,
                'domain' => $request->metadata['domain'],
                'duration' => microtime(true) - $startTime,
                'cache_hit' => $cacheHit
            ];
        } catch (\Throwable $e) {
            return [
                'agent_id' => $agentId,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'duration' => microtime(true) - $startTime
            ];
        }
    }

    /**
     * Simulate agent response (placeholder for real implementation).
     *
     * @param \Sapiensly\OpenaiAgents\Agent $agent The agent
     * @param string $userInput The user input
     * @return string The simulated response
     */
    private function simulateAgentResponse(\Sapiensly\OpenaiAgents\Agent $agent, string $userInput): string
    {
        $agentId = $agent->getId();
        
        // Simulate different agent responses based on agent type
        if (str_contains($agentId, 'math')) {
            return "Math agent response: I can help with mathematical calculations.";
        } elseif (str_contains($agentId, 'history')) {
            return "History agent response: I can provide historical information and context.";
        } elseif (str_contains($agentId, 'technical')) {
            return "Technical support agent response: I can help with technical issues and troubleshooting.";
        } elseif (str_contains($agentId, 'sales')) {
            return "Sales agent response: I can help with product information and pricing.";
        }

        return "Agent response: I can assist with your request.";
    }

    /**
     * Get agent name for display.
     *
     * @param string $agentId The agent ID
     * @return string The agent name
     */
    private function getAgentName(string $agentId): string
    {
        $nameMap = [
            'math_agent' => 'Mathematics Specialist',
            'history_agent' => 'History Specialist',
            'technical_support' => 'Technical Support',
            'sales_agent' => 'Sales Specialist'
        ];

        return $nameMap[$agentId] ?? $agentId;
    }
} 