<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Sapiensly\OpenaiAgents\Registry\AgentRegistry;
use Sapiensly\OpenaiAgents\State\ConversationStateManager;
use Sapiensly\OpenaiAgents\Security\SecurityManager;
use Sapiensly\OpenaiAgents\Metrics\MetricsCollector;

/**
 * Class FallbackStrategy
 *
 * Handles different fallback strategies when handoffs fail.
 * Provides multiple strategies for graceful degradation.
 */
class FallbackStrategy
{
    /**
     * Create a new FallbackStrategy instance.
     *
     * @param AgentRegistry $registry The agent registry
     * @param ConversationStateManager $stateManager The conversation state manager
     * @param SecurityManager $security The security manager
     * @param MetricsCollector $metrics The metrics collector
     * @param array $config The configuration array
     */
    public function __construct(
        private AgentRegistry $registry,
        private ConversationStateManager $stateManager,
        private SecurityManager $security,
        private MetricsCollector $metrics,
        private array $config
    ) {}

    /**
     * Execute a fallback strategy when a handoff fails.
     *
     * @param HandoffRequest $request The original handoff request
     * @param \Throwable $error The error that caused the failure
     * @return HandoffResult The result of the fallback strategy
     */
    public function executeFallback(HandoffRequest $request, \Throwable $error): HandoffResult
    {
        $strategy = $this->determineFallbackStrategy($request, $error);
        
        $this->metrics->recordFallbackAttempt($request, $error, $strategy);
        
        return match ($strategy) {
            'retry_with_different_agent' => $this->retryWithDifferentAgent($request, $error),
            'degrade_to_general' => $this->degradeToGeneralAgent($request, $error),
            'return_to_source' => $this->returnToSourceAgent($request, $error),
            'use_fallback_agent' => $this->useFallbackAgent($request, $error),
            default => $this->defaultFallback($request, $error)
        };
    }

    /**
     * Determine the appropriate fallback strategy based on the error and configuration.
     *
     * @param HandoffRequest $request The original handoff request
     * @param \Throwable $error The error that caused the failure
     * @return string The fallback strategy to use
     */
    private function determineFallbackStrategy(HandoffRequest $request, \Throwable $error): string
    {
        // If a fallback agent is specified, use it
        if ($request->fallbackAgentId) {
            return 'use_fallback_agent';
        }

        // Check configuration for default fallback strategy
        $defaultStrategy = $this->config['fallback_strategies']['default'] ?? 'degrade_to_general';
        
        // Check if there's a specific strategy for the target agent
        $agentSpecificStrategy = $this->config['fallback_strategies'][$request->targetAgentId] ?? null;
        
        if ($agentSpecificStrategy) {
            return $agentSpecificStrategy;
        }

        // Determine strategy based on error type
        if ($error instanceof HandoffSecurityException) {
            return 'return_to_source';
        }
        
        if ($error instanceof HandoffCapabilityException) {
            return 'retry_with_different_agent';
        }
        
        if ($error instanceof HandoffTimeoutException) {
            return 'degrade_to_general';
        }

        return $defaultStrategy;
    }

    /**
     * Retry with a different agent that has similar capabilities.
     *
     * @param HandoffRequest $request The original handoff request
     * @param \Throwable $error The error that caused the failure
     * @return HandoffResult The result of the retry
     */
    private function retryWithDifferentAgent(HandoffRequest $request, \Throwable $error): HandoffResult
    {
        // Find agents with similar capabilities
        $candidates = $this->registry->findAgentsByCapabilities($request->requiredCapabilities);
        
        // Remove the original target agent from candidates
        $candidates = array_filter($candidates, fn($agent) => $agent->getId() !== $request->targetAgentId);
        
        if (empty($candidates)) {
            return $this->degradeToGeneralAgent($request, $error);
        }

        // Select the first available candidate
        $alternativeAgent = $candidates[0];
        
        $fallbackRequest = new HandoffRequest(
            sourceAgentId: $request->sourceAgentId,
            targetAgentId: $alternativeAgent->getId(),
            conversationId: $request->conversationId,
            context: $request->context,
            metadata: array_merge($request->metadata, [
                'original_target' => $request->targetAgentId,
                'error' => $error->getMessage(),
                'fallback_strategy' => 'retry_with_different_agent'
            ]),
            reason: "Fallback: retrying with alternative agent after error: {$error->getMessage()}",
            priority: $request->priority + 1,
            requiredCapabilities: $request->requiredCapabilities,
            fallbackAgentId: null
        );

        return new HandoffResult(
            handoffId: $this->generateHandoffId(),
            status: 'success',
            targetAgentId: $alternativeAgent->getId(),
            context: $request->context
        );
    }

    /**
     * Degrade to the general agent.
     *
     * @param HandoffRequest $request The original handoff request
     * @param \Throwable $error The error that caused the failure
     * @return HandoffResult The result of the degradation
     */
    private function degradeToGeneralAgent(HandoffRequest $request, \Throwable $error): HandoffResult
    {
        $generalAgentId = $this->config['fallback_strategies']['default'] ?? 'general_agent';
        
        $fallbackRequest = new HandoffRequest(
            sourceAgentId: $request->sourceAgentId,
            targetAgentId: $generalAgentId,
            conversationId: $request->conversationId,
            context: $request->context,
            metadata: array_merge($request->metadata, [
                'original_target' => $request->targetAgentId,
                'error' => $error->getMessage(),
                'fallback_strategy' => 'degrade_to_general'
            ]),
            reason: "Fallback: degrading to general agent after error: {$error->getMessage()}",
            priority: $request->priority + 2,
            requiredCapabilities: [],
            fallbackAgentId: null
        );

        return new HandoffResult(
            handoffId: $this->generateHandoffId(),
            status: 'success',
            targetAgentId: $generalAgentId,
            context: $request->context
        );
    }

    /**
     * Return to the source agent.
     *
     * @param HandoffRequest $request The original handoff request
     * @param \Throwable $error The error that caused the failure
     * @return HandoffResult The result of returning to source
     */
    private function returnToSourceAgent(HandoffRequest $request, \Throwable $error): HandoffResult
    {
        return new HandoffResult(
            handoffId: $this->generateHandoffId(),
            status: 'failed',
            targetAgentId: $request->sourceAgentId,
            errorMessage: "Returned to source agent due to handoff failure: {$error->getMessage()}"
        );
    }

    /**
     * Use the specified fallback agent.
     *
     * @param HandoffRequest $request The original handoff request
     * @param \Throwable $error The error that caused the failure
     * @return HandoffResult The result of using fallback agent
     */
    private function useFallbackAgent(HandoffRequest $request, \Throwable $error): HandoffResult
    {
        $fallbackAgent = $this->registry->getAgent($request->fallbackAgentId);
        
        if (!$fallbackAgent) {
            return $this->degradeToGeneralAgent($request, $error);
        }

        return new HandoffResult(
            handoffId: $this->generateHandoffId(),
            status: 'success',
            targetAgentId: $request->fallbackAgentId,
            context: $request->context
        );
    }

    /**
     * Default fallback strategy.
     *
     * @param HandoffRequest $request The original handoff request
     * @param \Throwable $error The error that caused the failure
     * @return HandoffResult The result of the default fallback
     */
    private function defaultFallback(HandoffRequest $request, \Throwable $error): HandoffResult
    {
        return new HandoffResult(
            handoffId: $this->generateHandoffId(),
            status: 'failed',
            targetAgentId: $request->targetAgentId,
            errorMessage: "Handoff failed and no fallback strategy available: {$error->getMessage()}"
        );
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