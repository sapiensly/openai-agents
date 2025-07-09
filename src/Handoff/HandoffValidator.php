<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Sapiensly\OpenaiAgents\Registry\AgentRegistry;
use Sapiensly\OpenaiAgents\Security\SecurityManager;
use Sapiensly\OpenaiAgents\State\ConversationStateManager;

/**
 * Class HandoffValidator
 *
 * Provides comprehensive validation for handoff requests before execution.
 * Validates permissions, agent availability, circular handoffs, and limits.
 */
class HandoffValidator
{
    /**
     * Create a new HandoffValidator instance.
     *
     * @param AgentRegistry $registry The agent registry
     * @param SecurityManager $security The security manager
     * @param ConversationStateManager $stateManager The conversation state manager
     * @param array $config The configuration array
     */
    public function __construct(
        private AgentRegistry $registry,
        private SecurityManager $security,
        private ConversationStateManager $stateManager,
        private array $config
    ) {}

    /**
     * Validate a handoff request.
     *
     * @param HandoffRequest $request The handoff request to validate
     * @return ValidationResult The validation result
     */
    public function validateHandoff(HandoffRequest $request): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // 1. Validate permissions
        if (!$this->validatePermissions($request)) {
            $errors[] = "Permission denied for handoff from {$request->sourceAgentId} to {$request->targetAgentId}";
        }

        // 2. Validate target agent exists and is available
        if (!$this->validateTargetAgent($request)) {
            $errors[] = "Target agent '{$request->targetAgentId}' not found or not available";
        }

        // 3. Validate no circular handoffs
        if ($this->detectCircularHandoff($request)) {
            $errors[] = "Circular handoff detected: {$request->sourceAgentId} -> {$request->targetAgentId}";
        }

        // 4. Validate handoff limits
        if ($this->exceedsHandoffLimit($request)) {
            $errors[] = "Handoff limit exceeded for conversation {$request->conversationId}";
        }

        // 5. Validate required capabilities
        if (!empty($request->requiredCapabilities) && !$this->validateCapabilities($request)) {
            $errors[] = "Target agent does not have required capabilities: " . implode(', ', $request->requiredCapabilities);
        }

        // 6. Validate context size (warnings only)
        if ($this->contextTooLarge($request)) {
            $warnings[] = "Context size is large, may impact performance";
            // Nueva lógica: elevar a error si excede el doble del máximo permitido
            $contextSize = strlen(json_encode($request->context));
            $maxContextSize = $this->config['max_context_size'] ?? 10000;
            if ($contextSize > ($maxContextSize * 2)) {
                $errors[] = "Context size exceeds the hard limit (" . ($maxContextSize * 2) . " bytes)";
            }
        }

        // 7. Validate fallback agent if specified
        if ($request->fallbackAgentId && !$this->validateFallbackAgent($request)) {
            $warnings[] = "Fallback agent '{$request->fallbackAgentId}' not found or not available";
            // New logic: raise to error if fallback agent is required but doesn't exist
            $errors[] = "Fallback agent '{$request->fallbackAgentId}' does not exist or is not available";
        }

        return new ValidationResult(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings
        );
    }

    /**
     * Validate permissions for the handoff.
     *
     * @param HandoffRequest $request The handoff request
     * @return bool True if permissions are valid
     */
    private function validatePermissions(HandoffRequest $request): bool
    {
        try {
            $this->security->validateHandoffPermission($request->sourceAgentId, $request->targetAgentId);
            return true;
        } catch (HandoffSecurityException $e) {
            return false;
        }
    }

    /**
     * Validate that the target agent exists and is available.
     *
     * @param HandoffRequest $request The handoff request
     * @return bool True if target agent is valid
     */
    private function validateTargetAgent(HandoffRequest $request): bool
    {
        $targetAgent = $this->registry->getAgent($request->targetAgentId);
        
        if (!$targetAgent) {
            return false;
        }

        // Check if agent is available (not busy, online, etc.)
        // This is a basic check - in a real implementation you might check agent status
        return true;
    }

    /**
     * Detect circular handoffs in the conversation history.
     *
     * @param HandoffRequest $request The handoff request
     * @return bool True if circular handoff is detected
     */
    private function detectCircularHandoff(HandoffRequest $request): bool
    {
        $handoffHistory = $this->stateManager->getHandoffHistory($request->conversationId);
        
        // Check for immediate circular handoff
        if ($request->sourceAgentId === $request->targetAgentId) {
            return true;
        }

        // Check for circular patterns in recent history
        $recentHandoffs = array_slice($handoffHistory, -3); // Check last 3 handoffs
        
        foreach ($recentHandoffs as $handoff) {
            if (isset($handoff['source']) && isset($handoff['target'])) {
                // Check if we're going back to a recent source
                if ($handoff['source'] === $request->targetAgentId && $handoff['target'] === $request->sourceAgentId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if handoff limit is exceeded.
     *
     * @param HandoffRequest $request The handoff request
     * @return bool True if limit is exceeded
     */
    private function exceedsHandoffLimit(HandoffRequest $request): bool
    {
        $maxHandoffs = $this->config['max_handoffs_per_conversation'] ?? 10;
        $handoffHistory = $this->stateManager->getHandoffHistory($request->conversationId);
        
        return count($handoffHistory) >= $maxHandoffs;
    }

    /**
     * Validate that the target agent has required capabilities.
     *
     * @param HandoffRequest $request The handoff request
     * @return bool True if capabilities are valid
     */
    private function validateCapabilities(HandoffRequest $request): bool
    {
        $targetAgent = $this->registry->getAgent($request->targetAgentId);
        
        if (!$targetAgent) {
            return false;
        }

        $agentCapabilities = $this->registry->getAgentCapabilities($request->targetAgentId);
        
        foreach ($request->requiredCapabilities as $requiredCapability) {
            if (!in_array($requiredCapability, $agentCapabilities)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if context is too large.
     *
     * @param HandoffRequest $request The handoff request
     * @return bool True if context is too large
     */
    private function contextTooLarge(HandoffRequest $request): bool
    {
        $contextSize = strlen(json_encode($request->context));
        $maxContextSize = $this->config['max_context_size'] ?? 10000; // 10KB default
        
        return $contextSize > $maxContextSize;
    }

    /**
     * Validate fallback agent if specified.
     *
     * @param HandoffRequest $request The handoff request
     * @return bool True if fallback agent is valid
     */
    private function validateFallbackAgent(HandoffRequest $request): bool
    {
        $fallbackAgent = $this->registry->getAgent($request->fallbackAgentId);
        return $fallbackAgent !== null;
    }
} 