<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use Illuminate\Support\Facades\App;
use OpenAI\Factory;
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;
use Sapiensly\OpenaiAgents\Tests\MockOpenAI;
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
     * @param array|null $options The configuration options for the Agent. Defaults to `null`.
     * @param string|null $systemPrompt The system prompt to initialize the Agent. Defaults to `null`.
     * @return Agent A new Agent instance.
     */
    public function agent(array|null $options = null, string|null $systemPrompt = null): Agent
    {
        $options ??= [];
        $options = array_replace_recursive($this->config['default'] ?? [], $options);

        // Check if we're in a test environment using the mock class
        if (class_exists(MockOpenAI::class) &&
            MockOpenAI::$factory !== null) {
            $client = MockOpenAI::factory()->withApiKey($this->config['api_key'])->make();
        } else {
            $client = (new Factory())->withApiKey($this->config['api_key'])->make();
        }

        return new Agent($client, $options, $systemPrompt);
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
    public function runner(Agent|null $agent = null, int|null $maxTurns = null): Runner
    {
        $agent ??= $this->agent();

        // Create the runner with the agent and max turns
        $runner = new Runner(
            $agent,
            $maxTurns,
            App::make(Tracing::class),
            null
        );

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
