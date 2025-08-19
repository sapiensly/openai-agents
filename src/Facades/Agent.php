<?php

namespace Sapiensly\OpenaiAgents\Facades;

use Illuminate\Support\Facades\Facade;
use Sapiensly\OpenaiAgents\Agent as AgentClass;
use Sapiensly\OpenaiAgents\Runner;

/**
 * @method static string simpleChat(string $message, array $options = [])
 * @method static Runner runner(array $options = [])
 * @method static AgentClass use(string $agentName)
 * @method static AgentClass create(array $config)
 * @method static AgentClass agent(array $options = [], string $systemPrompt = null)
 * @method static AgentClass persistent(string $conversationId = null, array $options = null)
 * @method static AgentClass continueConversation(string $conversationId, array $options = null)
 * @method static AgentClass newConversation(array $options = null)
 * @method static Runner createRunner(AgentClass $agent = null, int $maxTurns = null)
 * @method static int getProgressiveLevel()
 * @method static bool isProgressiveFeatureEnabled(string $feature)
 * @method static AgentClass autoConfigureAgent(AgentClass $agent)
 *
 * @see \Sapiensly\OpenaiAgents\Agent
 * @see \Sapiensly\OpenaiAgents\AgentManager
 */
class Agent extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'agent';
    }

    /**
     * Get the agent manager instance.
     *
     * @return \Sapiensly\OpenaiAgents\AgentManager
     */
    public static function manager()
    {
        return app('agent.manager');
    }

    /**
     * Create a simple agent instance.
     *
     * @param array $options
     * @param string|null $systemPrompt
     * @return \Sapiensly\OpenaiAgents\Agent
     */
    public static function agent(array $options = [], ?string $systemPrompt = null)
    {
        return static::manager()->agent($options, $systemPrompt);
    }

    /**
     * Simple chat method for quick responses.
     *
     * @param string $message
     * @param array $options
     * @return string
     */
    public static function simpleChat(string $message, array $options = []): string
    {
        $agent = static::agent($options);
        return $agent->chat($message);
    }

    /**
     * Create a runner instance.
     *
     * @param \Sapiensly\OpenaiAgents\Agent|null $agent
     * @param int|null $maxTurns
     * @return \Sapiensly\OpenaiAgents\Runner
     */
    public static function createRunner(?AgentClass $agent = null, ?int $maxTurns = null)
    {
        return static::manager()->runner($agent, $maxTurns);
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
     * @param \Sapiensly\OpenaiAgents\Agent $agent
     * @return \Sapiensly\OpenaiAgents\Agent
     */
    public static function autoConfigureAgent(AgentClass $agent): AgentClass
    {
        return static::manager()->autoConfigureAgent($agent);
    }
}
