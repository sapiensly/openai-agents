<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Illuminate\Support\Facades\Log;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\Registry\AgentRegistry;
use Sapiensly\OpenaiAgents\State\ConversationStateManager;
use Sapiensly\OpenaiAgents\Security\SecurityManager;
use Sapiensly\OpenaiAgents\Security\HandoffSecurityException;
use Sapiensly\OpenaiAgents\Metrics\MetricsCollector;
use Random\RandomException;
use Sapiensly\OpenaiAgents\Handoff\ReversibleHandoffManager;
use Sapiensly\OpenaiAgents\Handoff\ParallelHandoffManager;
use Sapiensly\OpenaiAgents\Handoff\ParallelHandoffResult;
use Sapiensly\OpenaiAgents\Handoff\IntelligentCacheManager;
use Sapiensly\OpenaiAgents\Handoff\AsyncHandoffManager;
use Sapiensly\OpenaiAgents\Tracing\TracingManager;

/**
 * Class HandoffOrchestrator
 *
 * Central component that orchestrates the handoff process between agents.
 * Uses the AgentRegistry, ConversationStateManager, SecurityManager, and MetricsCollector
 * to manage the handoff process.
 */
class HandoffOrchestrator
{
    /**
     * The agent registry.
     *
     * @var AgentRegistry
     */
    private AgentRegistry $registry;

    /**
     * The conversation state manager.
     *
     * @var ConversationStateManager
     */
    private ConversationStateManager $stateManager;

    /**
     * The security manager.
     *
     * @var SecurityManager
     */
    private SecurityManager $security;

    /**
     * The metrics collector.
     *
     * @var MetricsCollector
     */
    private MetricsCollector $metrics;

    /**
     * The configuration array.
     *
     * @var array
     */
    private array $config;

    /**
     * The handoff validator.
     *
     * @var HandoffValidator
     */
    private HandoffValidator $validator;

    /**
     * The fallback strategy handler.
     *
     * @var FallbackStrategy
     */
    private FallbackStrategy $fallbackStrategy;

    /**
     * The context analyzer for intelligent handoffs.
     *
     * @var ContextAnalyzer
     */
    private ContextAnalyzer $contextAnalyzer;

    /**
     * The reversible handoff manager.
     *
     * @var ReversibleHandoffManager
     */
    private ReversibleHandoffManager $reversibleHandoffManager;

    /**
     * The parallel handoff manager.
     *
     * @var ParallelHandoffManager
     */
    private ParallelHandoffManager $parallelHandoffManager;

    /**
     * The intelligent cache manager.
     *
     * @var IntelligentCacheManager
     */
    private IntelligentCacheManager $cacheManager;

    /**
     * The async handoff manager.
     *
     * @var AsyncHandoffManager
     */
    private AsyncHandoffManager $asyncHandoffManager;

    /**
     * The tracing manager for distributed tracing.
     *
     * @var TracingManager
     */
    private TracingManager $tracing;

    /**
     * Create a new HandoffOrchestrator instance.
     *
     * @param AgentRegistry $registry The agent registry
     * @param ConversationStateManager $stateManager The conversation state manager
     * @param SecurityManager $security The security manager
     * @param MetricsCollector $metrics The metrics collector
     * @param array $config The configuration array
     * @param TracingManager|null $tracing The tracing manager (optional)
     */
    public function __construct(
        AgentRegistry $registry,
        ConversationStateManager $stateManager,
        SecurityManager $security,
        MetricsCollector $metrics,
        array $config = [],
        ?TracingManager $tracing = null
    ) {
        $this->registry = $registry;
        $this->stateManager = $stateManager;
        $this->security = $security;
        $this->metrics = $metrics;
        $this->config = $config;
        $this->tracing = $tracing ?? new TracingManager();
        
        // Initialize validator, fallback strategy, and context analyzer
        $this->validator = new HandoffValidator($registry, $security, $stateManager, $config);
        $this->fallbackStrategy = new FallbackStrategy($registry, $stateManager, $security, $metrics, $config);
        $this->contextAnalyzer = new ContextAnalyzer($registry, $config);
        $this->reversibleHandoffManager = new ReversibleHandoffManager($stateManager, $metrics);
        $this->parallelHandoffManager = new ParallelHandoffManager($registry, $stateManager, $metrics);
        $this->cacheManager = new IntelligentCacheManager();
        $this->asyncHandoffManager = new AsyncHandoffManager();
    }

    /**
     * Handle a handoff request.
     *
     * @param HandoffRequest $request The handoff request
     * @return HandoffResult The handoff result
     */
    public function handleHandoff(HandoffRequest $request): HandoffResult
    {
        $spanId = $this->tracing->startSpan('handoff_execution', [
            'source_agent' => $request->sourceAgentId,
            'target_agent' => $request->targetAgentId,
            'conversation_id' => $request->conversationId,
            'priority' => $request->priority
        ]);

        try {
            // Validate the handoff request
            $validationSpan = $this->tracing->startSpan('handoff_validation', [
                'request_id' => uniqid('handoff_', true)
            ]);

            $validationResult = $this->validator->validateHandoff($request);
            $this->tracing->endSpan(['valid' => $validationResult->isValid()]);

            if (!$validationResult->isValid()) {
                $this->tracing->endSpan(['success' => false, 'error' => 'validation_failed']);
                $handoffId = uniqid('handoff_', true);
                return new HandoffResult(
                    $handoffId,
                    'failed',
                    $request->targetAgentId,
                    $validationResult->getErrorsAsString(),
                    ['trace_id' => $this->tracing->getTraceId()]
                );
            }

            // Check permissions
            $permissionSpan = $this->tracing->startSpan('permission_check', [
                'source_agent' => $request->sourceAgentId,
                'target_agent' => $request->targetAgentId
            ]);

            try {
                $this->security->validateHandoffPermission($request->sourceAgentId, $request->targetAgentId);
            } catch (HandoffSecurityException $e) {
                $this->tracing->endSpan(['permitted' => false]);
                $this->tracing->endSpan(['success' => false, 'error' => 'permission_denied']);
                $handoffId = uniqid('handoff_', true);
                return new HandoffResult(
                    $handoffId,
                    'failed',
                    $request->targetAgentId,
                    $e->getMessage(),
                    ['trace_id' => $this->tracing->getTraceId()]
                );
            }

            $this->tracing->endSpan(['permitted' => true]);

            // Get the target agent
            $targetAgent = $this->registry->getAgent($request->targetAgentId);
            if (!$targetAgent) {
                $this->tracing->endSpan(['success' => false, 'error' => 'agent_not_found']);
                $handoffId = uniqid('handoff_', true);
                return new HandoffResult(
                    $handoffId,
                    'failed',
                    $request->targetAgentId,
                    "Target agent {$request->targetAgentId} not found",
                    ['trace_id' => $this->tracing->getTraceId()]
                );
            }

            // Save handoff state
            $stateSpan = $this->tracing->startSpan('state_save', [
                'conversation_id' => $request->conversationId
            ]);

            $this->stateManager->saveHandoffState(
                $request->conversationId,
                $request->sourceAgentId,
                $request->targetAgentId,
                $request->context
            );

            $this->tracing->endSpan(['state_saved' => true]);

            // Record metrics
            $this->metrics->recordHandoffStart($request);

            // Create success result
            $handoffId = uniqid('handoff_', true);
            $result = new HandoffResult(
                $handoffId,
                'success',
                $request->targetAgentId,
                null,
                ['trace_id' => $this->tracing->getTraceId()]
            );

            $this->metrics->recordHandoffSuccess($request, $result, 0.0);
            $this->tracing->endSpan(['success' => true]);

            return $result;

        } catch (\Throwable $e) {
            $this->metrics->recordHandoffFailure($request, $e);
            $this->tracing->endSpan(['success' => false, 'error' => $e->getMessage()]);
            
            $handoffId = uniqid('handoff_', true);
            return new HandoffResult(
                $handoffId,
                'failed',
                $request->targetAgentId,
                $e->getMessage(),
                ['trace_id' => $this->tracing->getTraceId()]
            );
        }
    }

    /**
     * Suggest a handoff based on user input and context analysis.
     *
     * @param string $userInput The user's input
     * @param string $currentAgentId The current agent ID
     * @param string $conversationId The conversation ID
     * @param array $context The conversation context
     * @return HandoffSuggestion|null The suggested handoff, or null if no handoff needed
     */
    public function suggestHandoff(string $userInput, string $currentAgentId, string $conversationId, array $context = [], bool $cacheDebug = false): ?HandoffSuggestion
    {
        // Search in cache first
        $cached = $this->cacheManager->getCachedHandoffSuggestion($userInput, $context);
        if ($cached) {
            if ($cacheDebug) {
                Log::info("[HandoffOrchestrator] Cache HIT for handoff suggestion");
            }
            return $cached;
        }
        // If not found, analyze and cache
        $suggestion = $this->contextAnalyzer->analyzeAndSuggestHandoff($userInput, $currentAgentId);
        if ($suggestion) {
            $this->cacheManager->cacheHandoffSuggestion($userInput, $context, $suggestion);
        }
        if ($cacheDebug) {
            Log::info("[HandoffOrchestrator] Cache MISS for handoff suggestion, caching result.");
        }
        return $suggestion;
    }

    /**
     * Handle an intelligent handoff based on context analysis.
     *
     * @param string $userInput The user's input
     * @param string $currentAgentId The current agent ID
     * @param string $conversationId The conversation ID
     * @param array $context The conversation context
     * @param float $confidenceThreshold The minimum confidence threshold (default: 0.7)
     * @return HandoffResult|null The handoff result, or null if no handoff needed
     */
    public function handleIntelligentHandoff(
        string $userInput, 
        string $currentAgentId, 
        string $conversationId, 
        array $context = [], 
        float $confidenceThreshold = 0.7
    ): ?HandoffResult {
        // Get suggestion from context analyzer
        $suggestion = $this->suggestHandoff($userInput, $currentAgentId, $conversationId, $context);
        
        if (!$suggestion) {
            return null;
        }
        
        // Check if confidence meets threshold
        if ($suggestion->confidence < $confidenceThreshold) {
            return null;
        }
        
        // Convert suggestion to handoff request
        $request = $suggestion->toHandoffRequest($currentAgentId, $conversationId, $context);
        
        // Handle the handoff
        return $this->handleHandoff($request);
    }

    /**
     * Handle a hybrid handoff that combines intelligent and manual modes.
     * First tries intelligent handoff, then falls back to manual if needed.
     *
     * @param string $userInput The user's input
     * @param string $currentAgentId The current agent ID
     * @param string $conversationId The conversation ID
     * @param array $context The conversation context
     * @param float $confidenceThreshold The minimum confidence threshold for intelligent handoff
     * @return HandoffResult|null The handoff result, or null if no handoff needed
     */
    public function handleHybridHandoff(
        string $userInput, 
        string $currentAgentId, 
        string $conversationId, 
        array $context = [], 
        float $confidenceThreshold = 0.7
    ): ?HandoffResult {
        // Step 1: Try intelligent handoff first
        if ($this->config['intelligent']['enabled'] ?? false) {
            $intelligentResult = $this->handleIntelligentHandoff($userInput, $currentAgentId, $conversationId, $context, $confidenceThreshold);
            if ($intelligentResult && $intelligentResult->isSuccess()) {
                return $intelligentResult;
            }
        }
        
        // Step 2: If intelligent handoff didn't work, let the agent decide manually
        // This will be handled by the agent's response parsing
        return null;
    }

    /**
     * Check if intelligent handoff is enabled.
     *
     * @return bool True if intelligent handoff is enabled
     */
    public function isIntelligentHandoffEnabled(): bool
    {
        return $this->config['intelligent']['enabled'] ?? false;
    }

    /**
     * Check if manual handoff is enabled.
     *
     * @return bool True if manual handoff is enabled
     */
    public function isManualHandoffEnabled(): bool
    {
        return $this->config['manual']['enabled'] ?? false;
    }

    /**
     * Get the current handoff mode configuration.
     *
     * @return array The handoff mode configuration
     */
    public function getHandoffModeConfig(): array
    {
        return [
            'intelligent' => $this->isIntelligentHandoffEnabled(),
            'manual' => $this->isManualHandoffEnabled(),
            'advanced' => $this->config['advanced'] ?? false,
        ];
    }

    /**
     * Reverse the last handoff for a conversation.
     *
     * @param string $conversationId
     * @param string $currentAgentId
     * @param array $context
     * @return HandoffResult|null
     */
    public function reverseLastHandoff(string $conversationId, string $currentAgentId, array $context = []): ?HandoffResult
    {
        return $this->reversibleHandoffManager->reverseLastHandoff($conversationId, $currentAgentId, $context);
    }

    /**
     * Execute parallel handoffs for a user input.
     *
     * @param string $userInput
     * @param string $conversationId
     * @return ParallelHandoffResult
     */
    public function executeParallelHandoffs(string $userInput, string $conversationId): ParallelHandoffResult
    {
        $requests = $this->parallelHandoffManager->analyzeParallelHandoffs($userInput);
        return $this->parallelHandoffManager->executeParallelHandoffs($requests, $conversationId);
    }

    /**
     * Find the optimal agent for a handoff request.
     *
     * @param HandoffRequest $request The handoff request
     * @return Agent|null The optimal agent, or null if none found
     */
    private function findOptimalAgent(HandoffRequest $request): ?Agent
    {
        // If there are required capabilities, find agents with those capabilities
        if (!empty($request->requiredCapabilities)) {
            $candidates = $this->registry->findAgentsByCapabilities($request->requiredCapabilities);

            if (!empty($candidates)) {
                // Apply load balancing and selection logic
                return $this->selectOptimalAgent($candidates, $request->priority);
            }
        }

        // If no match by capabilities or no capabilities specified, try by ID
        return $this->registry->getAgent($request->targetAgentId);
    }

    /**
     * Select the optimal agent from a list of candidates.
     *
     * @param array $candidates Array of candidate agents
     * @param int $priority The priority of the handoff
     * @return Agent|null The selected agent, or null if none available
     */
    private function selectOptimalAgent(array $candidates, int $priority): ?Agent
    {
        if (empty($candidates)) {
            return null;
        }

        // For now, just return the first candidate
        // In a more advanced implementation, this could use load balancing,
        // agent availability, or other criteria to select the best agent
        return $candidates[0];
    }

    /**
     * Handle fallback when a handoff fails.
     *
     * @param HandoffRequest $request The original handoff request
     * @param \Throwable $error The error that caused the failure
     * @return HandoffResult The result of the fallback handoff
     */
    private function handleFailover(HandoffRequest $request, \Throwable $error): HandoffResult
    {
        Log::info("[HandoffOrchestrator] Attempting fallback to {$request->fallbackAgentId} after error: {$error->getMessage()}");

        // Create a new request to the fallback agent
        $fallbackRequest = new HandoffRequest(
            sourceAgentId: $request->sourceAgentId,
            targetAgentId: $request->fallbackAgentId,
            conversationId: $request->conversationId,
            context: $request->context,
            metadata: array_merge($request->metadata, [
                'original_target' => $request->targetAgentId,
                'error' => $error->getMessage()
            ]),
            reason: "Fallback from failed handoff to {$request->targetAgentId}: {$error->getMessage()}",
            priority: $request->priority + 1, // Increase priority for fallback
            requiredCapabilities: [], // Don't require specific capabilities for fallback
            fallbackAgentId: null // No further fallback
        );

        // Handle the fallback request
        return $this->handleHandoff($fallbackRequest);
    }

    /**
     * Generate a unique handoff ID.
     *
     * @return string The generated handoff ID
     */
    private function generateHandoffId(): string
    {
        try {
            return 'ho_' . bin2hex(random_bytes(8));
        } catch (RandomException $e) {
            // Fallback if random_bytes fails
            return 'ho_' . uniqid('', true);
        }
    }

    /**
     * Get a cached handoff suggestion.
     */
    public function getCachedHandoffSuggestion(string $userInput, array $context = [])
    {
        return $this->cacheManager->getCachedHandoffSuggestion($userInput, $context);
    }
    /**
     * Cache a handoff suggestion.
     */
    public function cacheHandoffSuggestion(string $userInput, array $context, $suggestion): void
    {
        $this->cacheManager->cacheHandoffSuggestion($userInput, $context, $suggestion);
    }
    /**
     * Get a cached agent response.
     */
    public function getCachedAgentResponse(string $agentId, string $userInput, array $context = []): ?string
    {
        return $this->cacheManager->getCachedAgentResponse($agentId, $userInput, $context);
    }
    /**
     * Cache an agent response.
     */
    public function cacheAgentResponse(string $agentId, string $userInput, array $context, string $response): void
    {
        $this->cacheManager->cacheAgentResponse($agentId, $userInput, $context, $response);
    }
    /**
     * Get a cached parallel handoff result.
     */
    public function getCachedParallelResult(string $userInput, array $agentIds)
    {
        return $this->cacheManager->getCachedParallelResult($userInput, $agentIds);
    }
    /**
     * Cache a parallel handoff result.
     */
    public function cacheParallelResult(string $userInput, array $agentIds, $result): void
    {
        $this->cacheManager->cacheParallelResult($userInput, $agentIds, $result);
    }

    /**
     * Queue an asynchronous handoff.
     *
     * @param HandoffRequest $request The handoff request
     * @param array $options The async options
     * @return string The job ID
     */
    public function queueAsyncHandoff(HandoffRequest $request, array $options = []): string
    {
        return $this->asyncHandoffManager->dispatchAsyncHandoff($request, $options);
    }

    /**
     * Get the status of an async handoff job.
     *
     * @param string $jobId The job ID
     * @return array The job status
     */
    public function getAsyncHandoffStatus(string $jobId): array
    {
        return $this->asyncHandoffManager->getAsyncHandoffStatus($jobId);
    }

    /**
     * Cancel an async handoff job.
     *
     * @param string $jobId The job ID
     * @return bool True if cancelled successfully
     */
    public function cancelAsyncHandoff(string $jobId): bool
    {
        return $this->asyncHandoffManager->cancelAsyncHandoff($jobId);
    }

    /**
     * Get all active async handoff jobs.
     *
     * @param string $conversationId The conversation ID (optional)
     * @return array Array of active jobs
     */
    public function getActiveAsyncHandoffs(string $conversationId = null): array
    {
        return $this->asyncHandoffManager->getActiveAsyncHandoffs($conversationId);
    }

    /**
     * Get async handoff statistics.
     *
     * @return array The statistics
     */
    public function getAsyncHandoffStats(): array
    {
        return $this->asyncHandoffManager->getAsyncHandoffStats();
    }

    /**
     * Get the handoff validator instance.
     *
     * @return HandoffValidator
     */
    public function getValidator(): HandoffValidator
    {
        return $this->validator;
    }

    /**
     * Get the tracing manager.
     *
     * @return TracingManager
     */
    public function getTracing(): TracingManager
    {
        return $this->tracing;
    }
}
