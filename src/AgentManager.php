<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use Illuminate\Support\Facades\App;
use OpenAI\Factory as OpenAIFactory;
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;
use Sapiensly\OpenaiAgents\Tracing\Tracing;

class AgentManager
{
    /**
     * The configuration array.
     */
    protected array $config;

    /**
     * Create a new manager instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Create a new Agent instance.
     *
     * This method creates and returns an Agent instance using the provided options
     * and system prompt. It checks for the usage of a mock OpenAI class in a test
     * environment, otherwise defaults to creating a Factory-based client.
     *
     * @param AgentOptions|array|null $options The configuration options for the Agent. Defaults to `null`.
     * @param string|null $systemPrompt The system prompt to initialize the Agent. Defaults to `null`.
     * @return Agent A new Agent instance.
     */
    public function agent(AgentOptions|array|null $options = null, string|null $systemPrompt = null): Agent
    {
        // Convert an array to AgentOptions if needed
        if (is_array($options) || $options === null) {
            $options = $options === null ? [] : $options;
        }

        $defaultOptions = AgentOptions::fromArray($this->config['default'] ?? []);
        $agent_options = $defaultOptions->merge($options);


        $client = (new OpenAIFactory())->withApiKey($this->config['api_key'])->make();
        return new Agent($client, $agent_options, $systemPrompt);
    }

    /**
     * Create a new Agent instance for Runner (uses default_runner config).
     *
     * @param AgentOptions|array|null $options The configuration options for the Agent. Defaults to `null`.
     * @param string|null $instructions
     * @return Agent A new Agent instance with runner configuration.
     */
    public function runnerAgent(AgentOptions|array|null $options = null, string|null $instructions = null): Agent
    {
        // Convert an array to AgentOptions if needed
        if (is_array($options) || $options === null) {
            $options = $options === null ? [] : $options;
        }

        // Use default_runner configuration instead of default
        $defaultOptions = AgentOptions::fromArray($this->config['default_runner'] ?? []);
        $agent_options = $defaultOptions->merge($options);

        // Use runner instructions if no system prompt provided
        $baseInstructions = $instructions ?? config('agents.multi_agent.default_runner.instructions');

        $client = (new OpenAIFactory())->withApiKey($this->config['api_key'])->make();
        return new Agent($client, $agent_options, $baseInstructions);
    }

    /**
     * Create a new Runner instance.
     *
     * This method creates and returns a Runner instance using the provided agent
     * and max turns. If advanced handoff is enabled, it configures the Runner
     * with the HandoffOrchestrator.
     *
     * @param Agent|null $agent The agent to use in the runner. If null, a new agent will be created.
     * @param int|null $maxTurns The maximum number of turns allowed. Defaults to null.
     * @return Runner A new Runner instance.
     */
    public function runner(Agent|null $agent = null, string|null $name = null, int|null $maxTurns = null, string|null $instructions = null): Runner
    {
        // Use runnerAgent instead of agent for default creation
        $agent ??= $this->runnerAgent();
        $name ??=  config('agents.multi_agent.default_runner.name', 'runner_agent');
        $baseInstructions = $instructions ?? config('agents.multi_agent.default_runner.instructions');
        if ($baseInstructions) {
            $agent->setInstructions($baseInstructions);
        }
        // Create the runner with the agent and max turns
        try{
            $runner = new Runner(
                $agent,
                $name,
                $maxTurns,
                App::make(Tracing::class),
                null
            );
        }
        catch (\Exception $e) {
            throw new \RuntimeException("Failed to create Runner: " . $e->getMessage(), 0, $e);
        }

        // If advanced handoff is enabled, configure the runner with the orchestrator
        if ($this->config['handoff']['advanced'] ?? false) {
            $runner->setHandoffOrchestrator(App::make(HandoffOrchestrator::class));
        }

        return $runner;
    }

    /**
     * Get the current progressive enhancement level.
     *
     * @return int The current level (0-3)
     */
    public function getProgressiveLevel(): int
    {
        return $this->config['progressive']['level'] ?? 0;
    }

    /**
     * Check if a specific progressive feature is enabled.
     *
     * @param string $feature The feature to check
     * @return bool True if the feature is enabled
     */
    public function isProgressiveFeatureEnabled(string $feature): bool
    {
        return $this->config['progressive'][$feature] ?? false;
    }

    /**
     * Auto-configure the agent based on progressive level.
     *
     * @param Agent $agent The agent to configure
     * @return Agent The configured agent
     * @throws \Exception
     */
    public function autoConfigureAgent(Agent $agent): Agent
    {
        $level = $this->getProgressiveLevel();

        // Level 1+: Auto-configure tools
        if ($level >= 1 && $this->isProgressiveFeatureEnabled('auto_tools')) {
            $defaultTools = $this->config['progressive']['default_tools'] ?? [];
            $runner = new Runner($agent);

            foreach ($defaultTools as $toolName) {
                $runner->registerTool($toolName, $this->getDefaultTool($toolName));
            }
        }

        return $agent;
    }

    /**
     * Get default tool implementations for auto-configuration.
     *
     * @param string $toolName The tool name
     * @return callable The tool implementation
     */
    private function getDefaultTool(string $toolName): callable
    {
        $defaultTools = [
            'echo' => fn($text) => $text,
            'date' => fn() => date('Y-m-d H:i:s'),
            'calculator' => function($args) {
                $expr = $args['expression'] ?? '0';
                try {
                    return eval("return {$expr};");
                } catch (\Exception $e) {
                    return "Error calculating expression: {$expr}";
                }
            },
        ];

        return $defaultTools[$toolName] ?? fn($input) => "Tool {$toolName} not found";
    }
}
