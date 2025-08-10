<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use Exception;
use Fiber;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use ReflectionException;
use Sapiensly\OpenaiAgents\Guardrails\GuardrailException;
use Sapiensly\OpenaiAgents\Guardrails\InputGuardrail;
use Sapiensly\OpenaiAgents\Guardrails\OutputGuardrail;
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;
use Sapiensly\OpenaiAgents\Handoff\HandoffRequest;
use Sapiensly\OpenaiAgents\Handoff\HandoffResult;
use Sapiensly\OpenaiAgents\Tracing\Tracing;
use Sapiensly\OpenaiAgents\Tools\ToolCacheManager;
use Sapiensly\OpenaiAgents\Tools\ResponseCacheManager;
use Sapiensly\OpenaiAgents\Tools\ToolDefinition;
use Sapiensly\OpenaiAgents\Tools\ToolDefinitionBuilder;
use Sapiensly\OpenaiAgents\MCP\MCPManager;
use Sapiensly\OpenaiAgents\MCP\MCPTool;

/**
 * Class Runner
 *
 * Management and execution of agents, tools, and guardrails. Handles processing logic,
 * input/output validation, agent interaction, and tool registration for running a
 * sequence of operations via an agent.
 */
class Runner
{
    protected Agent $agent;
    protected Agent $runnerAgent;
    protected string $runnerAgentName;
    protected int $maxTurns;
    protected array $tools = [];
    protected array $namedAgents = [];
    protected mixed $outputType = null;
    protected array $inputGuardrails = [];
    protected array $outputGuardrails = [];
    protected Tracing|null $tracer = null;

    /**
     * Whether to use the advanced handoff implementation.
     *
     * @var bool
     */
    protected bool $useAdvancedHandoff;

    /**
     * The handoff orchestrator for advanced handoff.
     *
     * @var HandoffOrchestrator|null
     */
    protected HandoffOrchestrator|null $handoffOrchestrator = null;

    /**
     * Whether to force tool usage and reject direct responses.
     *
     * @var bool
     */
    protected bool $forceToolUsage = false;

    /**
     * Maximum retries when forcing tool usage.
     *
     * @var int
     */
    protected int $maxToolRetries = 3;

    /**
     * The conversation ID for advanced handoff.
     *
     * @var string|null
     */
    protected ?string $conversationId = null;

    /**
     * The number of turns used in the last run.
     *
     * @var int|null
     */
    protected ?int $turnCount = null;

    /**
     * The tool cache manager for caching tool results.
     *
     * @var ToolCacheManager|null
     */
    protected ?ToolCacheManager $toolCacheManager = null;

    /**
     * The response cache manager for caching complete responses.
     *
     * @var ResponseCacheManager|null
     */
    protected ?ResponseCacheManager $responseCacheManager = null;

    /**
     * The MCP manager for handling MCP servers and resources.
     *
     * @var MCPManager|null
     */
    protected ?MCPManager $mcpManager = null;

    /**
     * Constructor method for initializing the class with the provided parameters.
     *
     * @param Agent $agent The agent instance to be used.
     * @param int|null $maxTurns The maximum number of turns allowed, defaults to 5 if not specified.
     * @param Tracing|null $tracer An optional tracer instance for tracing operations.
     * @param mixed|null $outputType An optional output type specification.
     */
    public function __construct(Agent $agent, string $name, int|null $maxTurns = null, Tracing|null $tracer = null, mixed $outputType = null)
    {
        // check if name is already registered
        if (isset($this->namedAgents[$name])) {
            throw new Exception("Agent with name '{$name}' is already registered.");
        }


        $maxTurns ??= 5;
        $this->runnerAgent = $this->agent = $agent;
        $this->runnerAgentName = $name;
        $this->maxTurns = $maxTurns;
        $this->tracer = $tracer;
        $this->outputType = $outputType;

        // Check if advanced handoff is enabled
        $this->useAdvancedHandoff = Config::get('agents.handoff.advanced', false);

        // Generate a conversation ID if advanced handoff is enabled
        if ($this->useAdvancedHandoff) {
            $this->conversationId = 'conv_' . uniqid();
        }

        // Initialize tool cache manager
        $this->toolCacheManager = new ToolCacheManager(Config::get('agents.tools.cache.enabled', true));

        // Initialize response cache manager
        $this->responseCacheManager = new ResponseCacheManager(Config::get('agents.response_cache.enabled', true));

        // Pass cache manager to agent
        if ($this->toolCacheManager) {
            // Create new agent with cache manager
            $newAgent = new Agent(
                $this->agent->getClient(),
                $this->agent->getOptions(),
                null, // system prompt will be set from messages
                $this->agent->getId(),
                $this->toolCacheManager
            );

            // Copy messages from old agent
            $newAgent->setMessages($this->agent->getMessages());

            $this->agent = $newAgent;
        }

        // Register the agent with its name
        $this->registerAgent($name, $this->agent,
            config('agents.multi_agent.default_runner.use_me_when', 'default runner agent')
        );
    }


    /**
     * Registers a tool using the given name and callback.
     *
     * @param string $name The name of the tool to register.
     * @param callable $callback The callback function for the tool.
     */
    public function registerTool(string $name, callable $callback): void
    {
        $this->registerFunctionTool($name, $callback, []);
    }

    /**
     * Registers a function tool with the given name, callback function, and schema.
     *
     * @param string $name The name of the tool to be registered.
     * @param callable $fn The callback function associated with the tool.
     * @param array $schema The schema defining the structure or validation for the tool.
     */
    public function registerFunctionTool(string $name, callable $fn, array $schema): void
    {
        $this->tools[$name] = [
            'callback' => $fn,
            'schema' => $schema,
            'name' => $name,
        ];
    }

    /**
     * Registers a tool using the strong typing system.
     *
     * @param ToolDefinition $toolDefinition The tool definition with strong typing
     * @return void
     */
    public function registerTypedTool(ToolDefinition $toolDefinition): void
    {
        $this->tools[$toolDefinition->getName()] = [
            'callback' => $toolDefinition->getCallback(),
            'schema' => $toolDefinition->toArray(),
            'name' => $toolDefinition->getName(),
        ];
    }

    /**
     * Register an MCP server.
     *
     * @param string $name The server name
     * @param string $url The server URL
     * @param array $config Server configuration
     * @return self
     */
    public function registerMCPServer(string $name, string $url, array $config = []): self
    {
        if (!$this->mcpManager) {
            $this->mcpManager = new MCPManager();
        }

        $this->mcpManager->addServer($name, $url, $config);
        return $this;
    }

    /**
     * Register an MCP resource as a tool.
     *
     * @param string $serverName The server name
     * @param MCPTool $tool The MCP tool to register
     * @return self
     */
    public function registerMCPTool(string $serverName, MCPTool $tool): self
    {
        if (!$this->mcpManager) {
            $this->mcpManager = new MCPManager();
        }

        $this->mcpManager->addTool($tool);

        // Also register as a regular tool for the agent
        $this->registerFunctionTool($tool->getName(), function ($params) use ($tool) {
            return $tool->execute($params);
        }, $tool->getSchema());

        return $this;
    }

    /**
     * Get MCP tools for agent registration.
     *
     * @return array
     */
    public function getMCPTools(): array
    {
        if (!$this->mcpManager) {
            return [];
        }

        return $this->mcpManager->getToolDefinitions();
    }

    /**
     * Set the MCP manager.
     *
     * @param MCPManager $mcpManager The MCP manager
     * @return self
     */
    public function setMCPManager(MCPManager $mcpManager): self
    {
        $this->mcpManager = $mcpManager;
        return $this;
    }

    /**
     * Get the MCP manager.
     *
     * @return MCPManager|null
     */
    public function getMCPManager(): ?MCPManager
    {
        return $this->mcpManager;
    }

    /**
     * Execute an MCP tool.
     *
     * @param string $toolName The tool name
     * @param array $parameters The tool parameters
     * @return mixed
     */
    public function executeMCPTool(string $toolName, array $parameters = [])
    {
        if (!$this->mcpManager) {
            throw new Exception('MCP manager not initialized');
        }

        return $this->mcpManager->executeTool($toolName, $parameters);
    }

    /**
     * Test MCP server connections.
     *
     * @return array
     */
    public function testMCPConnections(): array
    {
        if (!$this->mcpManager) {
            return [];
        }

        return $this->mcpManager->testAllConnections();
    }

    /**
     * Get MCP statistics.
     *
     * @return array
     */
    public function getMCPStats(): array
    {
        if (!$this->mcpManager) {
            return [];
        }

        return $this->mcpManager->getStats();
    }

    /**
     * Stream a resource from an MCP server.
     *
     * @param string $serverName The server name
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @return iterable
     */
    public function streamMCPResource(string $serverName, string $resourceName, array $parameters = []): iterable
    {
        if (!$this->mcpManager) {
            throw new Exception('MCP Manager not initialized');
        }

        return $this->mcpManager->streamResource($serverName, $resourceName, $parameters);
    }

    /**
     * Subscribe to events from an MCP server.
     *
     * @param string $serverName The server name
     * @param string $eventType The event type
     * @param array $filters Optional filters
     * @return iterable
     */
    public function subscribeToMCPEvents(string $serverName, string $eventType, array $filters = []): iterable
    {
        if (!$this->mcpManager) {
            throw new Exception('MCP Manager not initialized');
        }

        return $this->mcpManager->subscribeToEvents($serverName, $eventType, $filters);
    }

    /**
     * Stream MCP resource with callback for real-time processing.
     *
     * @param string $serverName The server name
     * @param string $resourceName The resource name
     * @param array|null $parameters The resource parameters
     * @param callable|null $callback Callback function for each chunk
     * @return void
     * @throws Exception
     */
    public function streamMCPResourceWithCallback(string $serverName, string $resourceName, array|null $parameters = null, callable|null $callback = null): void
    {
        $parameters ??= [];
        if (!$this->mcpManager) {
            throw new Exception('MCP Manager not initialized');
        }

        $this->mcpManager->streamResourceWithCallback($serverName, $resourceName, $parameters, $callback);
    }

    /**
     * Get all MCP servers that support SSE.
     *
     * @return array
     */
    public function getMCPServersWithSSE(): array
    {
        if (!$this->mcpManager) {
            return [];
        }

        return $this->mcpManager->getServersWithSSE();
    }

    /**
     * Registers a simple tool with a string parameter using strong typing.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return void
     */
    public function registerStringTool(string $name, \Closure $callback, string $paramName, string $paramDescription = ''): void
    {
        $toolDefinition = ToolDefinition::withStringParam($name, $callback, $paramName, $paramDescription);
        $this->registerTypedTool($toolDefinition);
    }

    /**
     * Registers a simple tool with an integer parameter using strong typing.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return void
     */
    public function registerIntegerTool(string $name, \Closure $callback, string $paramName, string $paramDescription = ''): void
    {
        $toolDefinition = ToolDefinition::withIntegerParam($name, $callback, $paramName, $paramDescription);
        $this->registerTypedTool($toolDefinition);
    }

    /**
     * Registers a simple tool with a number parameter using strong typing.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return void
     */
    public function registerNumberTool(string $name, \Closure $callback, string $paramName, string $paramDescription = ''): void
    {
        $toolDefinition = ToolDefinition::withNumberParam($name, $callback, $paramName, $paramDescription);
        $this->registerTypedTool($toolDefinition);
    }

    /**
     * Registers a simple tool with a boolean parameter using strong typing.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return void
     */
    public function registerBooleanTool(string $name, \Closure $callback, string $paramName, string $paramDescription = ''): void
    {
        $toolDefinition = ToolDefinition::withBooleanParam($name, $callback, $paramName, $paramDescription);
        $this->registerTypedTool($toolDefinition);
    }

    /**
     * Registers a tool with no parameters using strong typing.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @return void
     */
    public function registerNoParamTool(string $name, \Closure $callback): void
    {
        $toolDefinition = ToolDefinition::withNoParams($name, $callback);
        $this->registerTypedTool($toolDefinition);
    }

    /**
     * Creates a tool definition builder for complex tools.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @return ToolDefinitionBuilder
     */
    public function toolBuilder(string $name, \Closure $callback): ToolDefinitionBuilder
    {
        return ToolDefinition::builder($name, $callback);
    }

    /**
     * Registers a callable function tool with its corresponding schema derived from its parameters.
     *
     * @param string $name Name to associate with the function.
     * @param callable $fn The function to be registered.
     *
     * @return void
     * @throws ReflectionException
     */
    public function registerAutoFunctionTool(string $name, callable $fn): void
    {
        $ref = new \ReflectionFunction($fn);
        $schema = ['type' => 'object', 'properties' => [], 'required' => []];
        foreach ($ref->getParameters() as $param) {
            $type = 'string';
            if ($param->hasType()) {
                $map = ['int' => 'integer', 'float' => 'number', 'bool' => 'boolean'];
                $t = $param->getType()->getName();
                $type = $map[$t] ?? 'string';
            }
            $schema['properties'][$param->getName()] = compact('type');
            if (!$param->isOptional()) {
                $schema['required'][] = $param->getName();
            }
        }
        if (empty($schema['required'])) {
            unset($schema['required']);
        }
        $this->registerFunctionTool($name, $fn, $schema);
    }

    /**
     * Registers an agent with a specified name.
     *
     * @param string $name The name to associate with the agent.
     * @param Agent $agent The agent instance to register.
     * @param string $useMeWhen A condition or instruction for when this agent should be used.
     *
     * @return void
     */
    public function registerAgent(string $name, Agent $agent, string $useMeWhen): void
    {
        $this->namedAgents[$name]['agent'] = $agent;
        $this->namedAgents[$name]['use_me_when'] = $useMeWhen;
        // Update instructions for the current agent, so it knows about the specialists
        $this->setMultiAgentInstruction($agent, $useMeWhen);
        // Finally, update all registered agents' instructions to include the specialists
        $this->addAgentToAllRegisteredAgentsInstructions($agent, $useMeWhen);
    }

    /**
     * Updates the instructions for the current agent to include registered specialists.
     *
     * This method constructs a section listing all registered agents and their capabilities
     * and appends it to the agent's existing instructions.
     */
    private function setMultiAgentInstruction($agent, string $useMeWhen): void
    {
        if (empty($this->namedAgents)) {
            return;
        }

        // Get append instructions from config
        $multiagentInstructions = config('agents.multi_agent.multiagent_instructions', '');

        // Build the updated instructions
        $addInstructions = $multiagentInstructions . "\n\nAVAILABLE SPECIALISTS:\n";
        $entry = '';

        foreach ($this->namedAgents as $name => $registeredAgent) {
            if($agent->getId() !== $registeredAgent['agent']->getId()) {
                $entry .= "- Transfer to {$name} when or if: {$registeredAgent['use_me_when']}\n";
            }
        }
        $addInstructions.= $entry;
        $agent->appendInstructions($addInstructions);

    }

    /**
     * Adds the current agent to all registered agents' instructions.
     *
     * This method ensures that all registered agents are aware of the current agent's capabilities
     * and can reference it in their instructions.
     */
    private function addAgentToAllRegisteredAgentsInstructions(Agent $agent, string $useMeWhen): void
    {
        if (empty($this->namedAgents)) {
            return;
        }

        // Build new agent entry:
        $entry = '';

        foreach ($this->namedAgents as $name => $registeredAgent) {
            if($agent->getId() === $registeredAgent['agent']->getId()) {
                $entry .= "- Transfer to {$name} when or if: {$registeredAgent['use_me_when']}\n";
            }
        }
        if (empty($entry)) {
            return; // No other agents to add
        }
        foreach ($this->namedAgents as $registeredAgent) {
            if($agent->getId() !== $registeredAgent['agent']->getId()) {
                $registeredAgent['agent']->appendInstructions($entry);
            }
        }
    }


    /**
     * Adds an input guardrail to the collection of input guardrails.
     *
     * @param InputGuardrail $guard The input guardrail instance to be added.
     *
     * @return void
     */
    public function addInputGuardrail(InputGuardrail $guard): void
    {
        $this->inputGuardrails[] = $guard;
    }

    /**
     * Adds an output guardrail to the collection of output guardrails.
     *
     * @param OutputGuardrail $guard The guardrail instance to be added.
     *
     * @return void
     */
    public function addOutputGuardrail(OutputGuardrail $guard): void
    {
        $this->outputGuardrails[] = $guard;
    }

    /**
     * Set the handoff orchestrator for advanced handoff.
     *
     * @param HandoffOrchestrator $orchestrator The handoff orchestrator
     * @return self Returns the Runner instance for method chaining
     */
    public function setHandoffOrchestrator(HandoffOrchestrator $orchestrator): self
    {
        $this->handoffOrchestrator = $orchestrator;
        return $this;
    }

    /**
     * Set the conversation ID for advanced handoff.
     *
     * @param string $conversationId The conversation ID
     * @return self Returns the Runner instance for method chaining
     */
    public function setConversationId(string $conversationId): self
    {
        $this->conversationId = $conversationId;
        return $this;
    }

    /**
     * Disable advanced handoff and use basic handoff instead.
     *
     * @return self Returns the Runner instance for method chaining
     */
    public function disableAdvancedHandoff(): self
    {
        $this->useAdvancedHandoff = false;
        $this->handoffOrchestrator = null;
        return $this;
    }

    /**
     * Get the current agent.
     *
     * @return Agent The current agent
     */
    public function getAgent(): Agent
    {
        return $this->agent;
    }

    /**
     * Executes the agent run loop, processing input, applying guardrails, and generating responses.
     *
     * This function manages the full lifecycle of an agent's processing loop. It validates input
     * through input guardrails, generates responses with the configured agent, validates output
     * through output guardrails, and handles advanced operations such as invoking tools or switching
     * agents based on response commands.
     *
     * @param string $message The initial input message to begin the agent's processing.
     *
     * @return string|array The final response from the agent. It can either be a raw string or an
     *                      array depending on the defined output type and matching conditions.
     *
     * @throws GuardrailException If any input or output validation fails during execution.
     */
    public function run(string $message): string|array
    {
        $spanId = $this->tracer?->startSpan('runner', ['max_turns' => $this->maxTurns]);

        // Check response cache first
        if ($this->responseCacheManager && !$this->responseCacheManager->shouldBypassCache($message)) {
            $context = [
                'agent_id' => $this->agent->getId(),
                'tools' => array_keys($this->tools),
                'max_turns' => $this->maxTurns,
                'conversation_id' => $this->conversationId
            ];

            $cachedResponse = $this->responseCacheManager->getCachedResponse($message, $context);
            if ($cachedResponse !== null) {
                Log::info("[Runner Debug] Using cached response for input: {$message}");
                $this->tracer?->endSpan($spanId);
                return $cachedResponse;
            }
        }

        $turn = 0;
        $input = $message;
        $response = '';
        while ($turn < $this->maxTurns) {
            foreach ($this->inputGuardrails as $guard) {
                try {
                    $input = $guard->validate($input);
                } catch (GuardrailException $e) {
                    $this->tracer?->recordEvent($spanId, [
                        'turn' => $turn + 1,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $toolDefs = array_values(array_filter($this->tools, fn($t) => !empty($t['schema'])));

            // Add MCP tools if available
            if ($this->mcpManager) {
                $mcpTools = $this->getMCPTools();
                $toolDefs = array_merge($toolDefs, $mcpTools);
            }

            // Use agent loop if tool usage is forced
            if ($this->forceToolUsage && !empty($toolDefs)) {
                $response = $this->runAgentLoop($input, $toolDefs);
            } else {
                $response = $this->agent->chat($input, $toolDefs, $this->outputType);
            }
            Log::info("[Runner Debug] Current agent ID: " . ($this->agent->getId() ?? 'unknown'));
            Log::info("[Runner Debug] Agent response: {$response}");

            foreach ($this->outputGuardrails as $guard) {
                try {
                    $response = $guard->validate($response);
                } catch (GuardrailException $e) {
                    $this->tracer?->recordEvent($spanId, [
                        'turn' => $turn + 1,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $this->tracer?->recordEvent($spanId, [
                'turn' => $turn + 1,
                'input' => $input,
                'output' => $response,
            ]);

            // Tool call
            if (preg_match('/\[\[tool:(\w+)(?:\s+([^]]+))?]]/', $response, $m)) {
                $name = $m[1];
                $arg = $m[2] ?? '';
                if (isset($this->tools[$name])) {
                    $tool = $this->tools[$name];
                    $args = $arg;
                    if (!empty($tool['schema'])) {
                        $decoded = json_decode($arg, true);
                        $args = $decoded ?? $arg;
                    }

                    // Check cache first
                    $cachedResult = null;
                    if ($this->toolCacheManager && !$this->toolCacheManager->shouldBypassCache($name, $args)) {
                        $cachedResult = $this->toolCacheManager->getCachedResult($name, $args);
                    }

                    if ($cachedResult !== null) {
                        $input = $cachedResult;
                        Log::info("[Runner Debug] Using cached result for tool: {$name}");
                    } else {
                        // Execute tool and cache result
                        $input = ($tool['callback'])($args);

                        if ($this->toolCacheManager && !$this->toolCacheManager->shouldBypassCache($name, $args)) {
                            $this->toolCacheManager->cacheResult($name, $args, $input);
                        }
                    }

                    $turn++;
                    continue;
                }
            }

            // Handoff chaining
            if (preg_match('/\[\[handoff:(.+?)(?:\s+(.+))?]]/', $response, $m)) {
                $targetName = trim($m[1]);
                $handoffData = isset($m[2]) ? json_decode($m[2], true) : [];
                Log::info("[Runner Debug] Handoff pattern matched. Target: {$targetName}, Data: " . json_encode($handoffData));
                Log::info("[Runner Debug] Available agents: " . implode(', ', array_keys($this->namedAgents)));
                Log::info("[Runner Debug] Current agent ID: " . ($this->agent->getId() ?? 'unknown'));
                Log::info("[Runner Debug] Target agent ID: " . isset($this->namedAgents[$targetName]['agent']) ? $this->namedAgents[$targetName]['agent']->getId() : 'not found');
                Log::info("[Runner Debug] Using Advanced Handoff: " . $this->useAdvancedHandoff);

                if(!isset($this->namedAgents[$targetName]['agent']) && !$this->useAdvancedHandoff) {
                    Log::error("[Runner Debug] Handoff target agent not found: {$targetName}");
                    $input = "Sorry, I couldn't transfer you to the requested agent: {$targetName}. Please try again.";
                    $turn++;
                    continue;
                }
                $targetAgent = $this->namedAgents[$targetName]['agent'];

                if ($this->useAdvancedHandoff && $this->handoffOrchestrator !== null) {
                    Log::info("[Runner Debug] Using advanced handoff implementation");
                    $handoffRequest = new HandoffRequest(
                        sourceAgentId: $this->agent->getId() ?? 'unknown',
                        targetAgentId: $targetAgent->getId() ?? 'unknown',
                        conversationId: $this->conversationId ?? 'conv_' . uniqid(),
                        context: [
                            'messages' => $this->agent->getMessages(),
                            'custom_data' => $handoffData ?? []
                        ],
                        metadata: [
                            'turn' => $turn,
                            'last_response' => $response
                        ],
                        reason: $handoffData['reason'] ?? null,
                        priority: $handoffData['priority'] ?? 1,
                        requiredCapabilities: $handoffData['capabilities'] ?? [],
                        fallbackAgentId: $handoffData['fallback'] ?? null
                    );
                    $result = $this->handoffOrchestrator->handleHandoff($handoffRequest, $this->agent, $targetAgent);
                    if ($result->isSuccess()) {
                        Log::info("[Runner Debug] Advanced handoff successful to agent: {$result->targetAgentId}");
                        $this->agent = $targetAgent;
                    } else {
                        Log::error("[Runner Debug] Advanced handoff failed: {$result->errorMessage}");
                        $input = "Sorry, I couldn't transfer you to the requested agent. Error: {$result->errorMessage}";
                        $turn++;
                        continue;
                    }
                } else {
                    Log::info("[Runner Debug] Using basic handoff implementation");
                    if (isset($this->namedAgents[$targetName]['agent'])) {
                        Log::info("[Runner Debug] Switching to registered agent: {$targetName}");
                        $this->agent = $this->namedAgents[$targetName]['agent'];
                        Log::info("[Runner Debug] Agent switched successfully to: {$targetName}");
                        Log::info("[Runner Debug] New agent ID: " . ($this->agent->getId() ?? 'unknown'));
                    } else {
                        Log::info("[Runner Debug] Creating new agent with system prompt: {$targetName}");
                        $this->agent = new Agent($this->agent->getClient(), [], $targetName);
                    }
                }
                // Handoff chaining: always pass the original message to the new agent
                $input = $message;
                $turn++;
                continue;
            }

            if ($this->outputType !== null && !$this->outputMatches($response)) {
                $input = '';
                $turn++;
                continue;
            }
            break;
        }
        $this->tracer?->endSpan($spanId);
        $this->turnCount = $turn;

        // Cache the final response
        if ($this->responseCacheManager && !$this->responseCacheManager->shouldBypassCache($message)) {
            $context = [
                'agent_id' => $this->agent->getId(),
                'tools' => array_keys($this->tools),
                'max_turns' => $this->maxTurns,
                'conversation_id' => $this->conversationId
            ];

            $finalResponse = $this->outputType !== null && $this->outputMatches($response)
                ? json_decode($response, true)
                : $response;

            $this->responseCacheManager->cacheResponse($message, $context, (string) $finalResponse);
        }

        if ($this->outputType !== null && $this->outputMatches($response)) {
            return json_decode($response, true);
        }
        return $response;
    }

    /**
     * Runs a task asynchronously using fibers.
     *
     * @param string $message The message to be processed.
     *
     * @return Fiber Returns a Fiber instance that executes the task.
     */
    public function runAsync(string $message): Fiber
    {
        return new Fiber(function () use ($message) {
            return $this->run($message);
        });
    }

    /**
     * Run the agent and yield streamed output chunks.
     *
     * @return iterable<int, string>
     */
    public function runStreamed(string $message): iterable
    {
        $toolDefs = array_values(array_filter($this->tools, fn($t) => !empty($t['schema'])));
        yield from $this->agent->chatStreamed($message, $toolDefs, $this->outputType);
    }

    /**
     * Validates if the given JSON content matches the expected output type schema.
     *
     * @param string $content The JSON content to be validated.
     *
     * @return bool Returns true if the content matches the output type schema, false otherwise.
     */
    protected function outputMatches(string $content): bool
    {
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        if (is_array($this->outputType) && isset($this->outputType['required'])) {
            foreach ($this->outputType['required'] as $key) {
                if (!array_key_exists($key, $data)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Get the number of turns used in the last run.
     *
     * @return int|null
     */
    public function getTurnCount(): ?int
    {
        return $this->turnCount;
    }

    /**
     * Get the named agents registered with this runner.
     *
     * @return array<string, Agent>
     */
    public function getNamedAgents(): array
    {
        return $this->namedAgents;
    }

    /**
     * Get the tool cache manager.
     *
     * @return ToolCacheManager|null
     */
    public function getToolCacheManager(): ?ToolCacheManager
    {
        return $this->toolCacheManager;
    }

    /**
     * Set the tool cache manager.
     *
     * @param ToolCacheManager $cacheManager The cache manager
     * @return self Returns the Runner instance for method chaining
     */
    public function setToolCacheManager(ToolCacheManager $cacheManager): self
    {
        $this->toolCacheManager = $cacheManager;
        return $this;
    }

    /**
     * Get tool cache statistics.
     *
     * @return array|null The cache statistics, or null if cache manager is not available
     */
    public function getToolCacheStats(): ?array
    {
        return $this->toolCacheManager?->getCacheStats();
    }

    /**
     * Get the response cache manager.
     *
     * @return ResponseCacheManager|null
     */
    public function getResponseCacheManager(): ?ResponseCacheManager
    {
        return $this->responseCacheManager;
    }

    /**
     * Set the response cache manager.
     *
     * @param ResponseCacheManager $cacheManager The cache manager
     * @return self Returns the Runner instance for method chaining
     */
    public function setResponseCacheManager(ResponseCacheManager $cacheManager): self
    {
        $this->responseCacheManager = $cacheManager;
        return $this;
    }

    /**
     * Get response cache statistics.
     *
     * @return array|null The cache statistics, or null if cache manager is not available
     */
    public function getResponseCacheStats(): ?array
    {
        return $this->responseCacheManager?->getCacheStats();
    }

    /**
     * Enable forcing tool usage and reject direct responses.
     *
     * @param bool $enabled Whether to force tool usage
     * @param int $maxRetries Maximum retries when forcing tool usage
     * @return self Returns the Runner instance for method chaining
     */
    public function forceToolUsage(bool $enabled = true, int $maxRetries = 3): self
    {
        $this->forceToolUsage = $enabled;
        $this->maxToolRetries = $maxRetries;
        return $this;
    }

    /**
     * Check if tool usage is being forced.
     *
     * @return bool
     */
    public function isToolUsageForced(): bool
    {
        return $this->forceToolUsage;
    }

    /**
     * Agent loop that forces tool usage when required.
     *
     * @param string $message The initial message
     * @param array $toolDefinitions The tool definitions
     * @return string The final response
     */
    protected function runAgentLoop(string $message, array $toolDefinitions): string
    {
        $retries = 0;
        $input = $message;

        while ($retries < $this->maxToolRetries) {
            $response = $this->agent->chat($input, $toolDefinitions, $this->outputType);

            // Check if response contains tool calls
            if (preg_match('/\[\[tool:(\w+)(?:\s+([^]]+))?]]/', $response, $m)) {
                // Tool call detected, process it normally
                $name = $m[1];
                $arg = $m[2] ?? '';

                if (isset($this->tools[$name])) {
                    $tool = $this->tools[$name];
                    $args = $arg;
                    if (!empty($tool['schema'])) {
                        $decoded = json_decode($arg, true);
                        $args = $decoded ?? $arg;
                    }

                    // Validate arguments
                    if (isset($tool['schema']) && is_array($tool['schema'])) {
                        $validatorClass = \Sapiensly\OpenaiAgents\Tools\ToolArgumentValidator::class;
                        $errors = $validatorClass::validate($args, $tool['schema']);
                        if (!empty($errors)) {
                            $errorMessage = "Argument validation error: " . implode('; ', $errors);
                            Log::info("[Agent Loop] Validation failed: {$errorMessage}");

                            // Ask the model to retry with corrected arguments
                            $input = "The function call failed with validation errors: {$errorMessage}. Please retry with valid arguments.";
                            $retries++;
                            continue;
                        }
                    }

                    // Check cache first
                    $cachedResult = null;
                    if ($this->toolCacheManager && !$this->toolCacheManager->shouldBypassCache($name, $args)) {
                        $cachedResult = $this->toolCacheManager->getCachedResult($name, $args);
                    }

                    if ($cachedResult !== null) {
                        $input = $cachedResult;
                        Log::info("[Agent Loop] Using cached result for tool: {$name}");
                    } else {
                        // Execute tool and cache result
                        $input = ($tool['callback'])($args);

                        if ($this->toolCacheManager && !$this->toolCacheManager->shouldBypassCache($name, $args)) {
                            $this->toolCacheManager->cacheResult($name, $args, $input);
                        }
                    }

                    $retries++;
                    continue;
                }
            }

            // No tool call detected
            if ($this->forceToolUsage && !empty($toolDefinitions)) {
                Log::info("[Agent Loop] No tool call detected, forcing retry. Attempt: " . ($retries + 1));
                $input = "You must use one of the available functions to answer this question. Do not respond directly. Available functions: " . implode(', ', array_column($toolDefinitions, 'name'));
                $retries++;
                continue;
            }

            // Valid response (either no tools required or tool call successful)
            return $response;
        }

        // Max retries reached
        if ($this->forceToolUsage) {
            return "Error: Maximum retries reached. The agent failed to use the required tools properly.";
        }

        return $response;
    }

    public function getCurrentAgent(): array|null
    {
        return [
            'id' => $this->agent->getId(),
            'name' => $this->getCurrentAgentName(),
            'model' => $this->agent->getOption('model'),
            'instructions' => $this->agent->getOption('instructions'),
            'tools' => $this->agent->getOption('tools')
        ];
    }

    /**
     * Find the name of the current agent by searching through named agents.
     *
     * @return string|null The agent name or null if not found
     */
    public function getCurrentAgentName(): ?string
    {
        $currentAgentId = $this->agent->getId();

        foreach ($this->getNamedAgents() as $name => $namedAgent) {
            if ($namedAgent['agent']->getId() === $currentAgentId) {
                return $name;
            }
        }

        return null;
    }

}
