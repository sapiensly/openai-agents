<?php

namespace Sapiensly\OpenaiAgents\Facades;

use Exception;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use Sapiensly\OpenaiAgents\Agent as AgentClass;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\AgentOptions;
use Sapiensly\OpenaiAgents\Runner;

/**
 * @method static Runner runner(array $options = [])
 * @method static AgentClass use(string $agentName)
 * @method static AgentClass create(array $config)
 *
 * @see AgentClass
 * @see AgentManager
 */
class Agent extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'agent';
    }

    /**
     * Get the agent manager instance.
     *
     * @return AgentManager
     */
    public static function manager(): AgentManager
    {
        return app('agent.manager');
    }

    /**
     * Create a simple agent instance.
     *
     * @param AgentOptions|array|null $options
     * @param string|null $systemPrompt
     * @return AgentClass
     */
    public static function agent(AgentOptions|array|null $options = null, string|null $systemPrompt = null): AgentClass
    {
        return static::manager()->agent($options, $systemPrompt);
    }

    /**
     * Simple chat method for quick responses.
     *
     * @param string $message
     * @param AgentOptions|array|null $options
     * @return string
     * @throws Exception
     */
    public static function simpleChat(string $message, AgentOptions|array|null $options = null): string
    {
        if (empty(trim($message))) {
            throw new InvalidArgumentException('Message cannot be empty.');
        }

        $agent = static::agent($options);
        return $agent->chat($message);

    }

    /**
     * Create a runner instance.
     *
     * @param AgentClass|null $agent
     * @param int|null $maxTurns
     * @return Runner
     */
    public static function createRunner(AgentClass|null $agent = null, ?int $maxTurns = null): Runner
    {
        // Name left null, pass maxTurns in correct position
        return static::manager()->runner($agent, null, $maxTurns);
    }

    /**
     * Get the current progressive enhancement level.
     *
     * @return int
     */
    public static function getProgressiveLevel(): int
    {
        return static::manager()->getProgressiveLevel();
    }

    /**
     * Check if a progressive feature is enabled.
     *
     * @param string $feature
     * @return bool
     */
    public static function isProgressiveFeatureEnabled(string $feature): bool
    {
        return static::manager()->isProgressiveFeatureEnabled($feature);
    }

    /**
     * Auto-configure an agent based on progressive level.
     *
     * @param AgentClass $agent
     * @return AgentClass
     */
    public static function autoConfigureAgent(AgentClass $agent): AgentClass
    {
        return static::manager()->autoConfigureAgent($agent);
    }

}
