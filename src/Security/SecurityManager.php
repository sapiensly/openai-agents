<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Security;

use Sapiensly\OpenaiAgents\Handoff\HandoffSecurityException;

/**
 * Class SecurityManager
 *
 * Manages security aspects of agent handoffs, including permission validation
 * and context data sanitization.
 */
class SecurityManager
{
    /**
     * Permission matrix defining which agents can hand off to which other agents.
     * Format: ['source_agent_id' => ['target_agent_id1', 'target_agent_id2', ...]]
     * Special value '*' means the agent can hand off to any other agent.
     *
     * @var array<string, array<string>>
     */
    private array $permissionMatrix = [];

    /**
     * List of sensitive keys that should be redacted in context data.
     *
     * @var array<string>
     */
    private array $sensitiveKeys = [];

    /**
     * Create a new SecurityManager instance.
     *
     * @param array $config Configuration array
     */
    public function __construct(array $config)
    {
        $this->permissionMatrix = $config['handoff']['permissions'] ?? [];
        $this->sensitiveKeys = $config['handoff']['sensitive_keys'] ?? [
            'password', 'token', 'secret', 'key', 'credential', 'ssn', 'credit_card'
        ];
    }

    /**
     * Validate that a source agent has permission to hand off to a target agent.
     *
     * @param string $sourceAgentId The ID of the source agent
     * @param string $targetAgentId The ID of the target agent
     * @return void
     * @throws HandoffSecurityException If the source agent does not have permission
     */
    public function validateHandoffPermission(string $sourceAgentId, string $targetAgentId): void
    {
        if (!$this->hasPermission($sourceAgentId, $targetAgentId)) {
            throw new HandoffSecurityException(
                "Agent {$sourceAgentId} does not have permission to hand off to {$targetAgentId}"
            );
        }
    }

    /**
     * Sanitize context data by redacting sensitive information.
     *
     * @param array $context The context data to sanitize
     * @return array The sanitized context data
     */
    public function sanitizeContext(array $context): array
    {
        return $this->recursiveSanitize($context, $this->sensitiveKeys);
    }

    /**
     * Check if a source agent has permission to hand off to a target agent.
     *
     * @param string $sourceAgentId The ID of the source agent
     * @param string $targetAgentId The ID of the target agent
     * @return bool True if the source agent has permission, false otherwise
     */
    private function hasPermission(string $sourceAgentId, string $targetAgentId): bool
    {
        // If the source agent has global permission, allow
        if (isset($this->permissionMatrix[$sourceAgentId]) &&
            in_array('*', $this->permissionMatrix[$sourceAgentId])) {
            return true;
        }

        // Check for specific permission
        return isset($this->permissionMatrix[$sourceAgentId]) &&
            in_array($targetAgentId, $this->permissionMatrix[$sourceAgentId]);
    }

    /**
     * Recursively sanitize an array by redacting sensitive information.
     *
     * @param array $data The data to sanitize
     * @param array $sensitiveKeys List of sensitive keys to redact
     * @return array The sanitized data
     */
    private function recursiveSanitize(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            // If the key is sensitive, redact the value
            if ($this->isSensitiveKey($key, $sensitiveKeys)) {
                $data[$key] = '[REDACTED]';
            }
            // If the value is an array, recursively sanitize it
            elseif (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value, $sensitiveKeys);
            }
        }

        return $data;
    }

    /**
     * Check if a key is sensitive and should be redacted.
     *
     * ✅ FIXED: Now accepts both string and int keys
     *
     * @param string|int $key The key to check
     * @param array $sensitiveKeys List of sensitive keys to check against
     * @return bool True if the key is sensitive, false otherwise
     */
    private function isSensitiveKey(string|int $key, array $sensitiveKeys): bool
    {
        // ✅ FIX: Skip numeric keys (array indices)
        if (is_int($key)) {
            return false;
        }

        $lowerKey = strtolower($key);
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (strpos($lowerKey, strtolower($sensitiveKey)) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a permission for a source agent to hand off to a target agent.
     *
     * @param string $sourceAgentId The ID of the source agent
     * @param string $targetAgentId The ID of the target agent
     * @return void
     */
    public function addPermission(string $sourceAgentId, string $targetAgentId): void
    {
        if (!isset($this->permissionMatrix[$sourceAgentId])) {
            $this->permissionMatrix[$sourceAgentId] = [];
        }

        if (!in_array($targetAgentId, $this->permissionMatrix[$sourceAgentId])) {
            $this->permissionMatrix[$sourceAgentId][] = $targetAgentId;
        }
    }

    /**
     * Remove a permission for a source agent to hand off to a target agent.
     *
     * @param string $sourceAgentId The ID of the source agent
     * @param string $targetAgentId The ID of the target agent
     * @return void
     */
    public function removePermission(string $sourceAgentId, string $targetAgentId): void
    {
        if (isset($this->permissionMatrix[$sourceAgentId])) {
            $index = array_search($targetAgentId, $this->permissionMatrix[$sourceAgentId]);
            if ($index !== false) {
                unset($this->permissionMatrix[$sourceAgentId][$index]);
                $this->permissionMatrix[$sourceAgentId] = array_values($this->permissionMatrix[$sourceAgentId]);
            }
        }
    }
}
