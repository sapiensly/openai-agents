<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use OpenAI\Client;
use Random\RandomException;
use ReflectionException;
use Sapiensly\OpenaiAgents\Events\AgentResponseGenerated;
use Sapiensly\OpenaiAgents\MCP\MCPManager;
use Sapiensly\OpenaiAgents\MCP\MCPServer;
use Sapiensly\OpenaiAgents\MCP\MCPTool;
use Sapiensly\OpenaiAgents\Tools\ToolCacheManager;
use Sapiensly\OpenaiAgents\Tools\VectorStoreTool;
use Sapiensly\OpenaiAgents\Traits\FunctionSchemaGenerator;

class Agent
{
    use FunctionSchemaGenerator;

    /**
     * The OpenAI client instance.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Configuration options for the agent.
     *
     * @var AgentOptions|null
     */
    protected AgentOptions|null $options;

    /**
     * Array of messages in the conversation.
     *
     * @var array
     */
    protected array $messages = [];

    /**
     * The expected output type/schema.
     *
     * @var mixed
     */
    protected mixed $outputType = null;

    /**
     * The unique identifier for this agent.
     *
     * @var string|null
     */
    protected string|null $id = null;

    /**
     * The tool cache manager for caching tool results.
     *
     * @var ToolCacheManager|null
     */
    protected ToolCacheManager|null $toolCacheManager = null;

    /**
     * Level 4: Autonomous Agent mode ("autonomous" or null)
     *
     * @var string|null
     */
    protected string|null $mode = null;

    /**
     * Level 4: Autonomy level ("low", "medium", "high")
     *
     * @var string|null
     */
    protected string|null $autonomyLevel = null;

    /**
     * Level 4: Capabilities for autonomous agents
     *
     * @var array|null
     */
    protected array|null $capabilities = null;

    /**
     * OpenAI official tools (code_interpreter, retrieval, web_search)
     *
     * @var array
     */
    protected array $openAITools = [];

    /**
     * RAG configuration for retrieval tool
     *
     * @var array|null
     */
    protected array|null $ragConfig = null;

    /**
     * Store function implementations for execution
     */
    protected array $functionImplementations = [];

    /**
     * Web search tool configuration.
     */
    protected bool $useWebSearch = false;

    /**
     * MCP manager instance
     *
     * @var MCPManager|null
     */
    protected MCPManager|null $mcpManager = null;

    /**
     * Whether MCP is enabled for this agent
     */
    protected bool $useMCP = false;

    /**
     * Total tokens used by the agent.
     */
    protected int $totalTokens = 0;


    /**
     * Create a new Agent instance.
     *
     * @param Client $client The OpenAI client instance
     * @param AgentOptions|array|null $options Configuration options for the agent
     * @param string|null $instructions
     * @param string|null $id
     * @param ToolCacheManager|null $toolCacheManager
     */
    public function __construct(Client $client, AgentOptions|array|null $options = null, string|null $instructions = null, string|null $id = null, ToolCacheManager|null $toolCacheManager = null)
    {
        $agentOptions = new AgentOptions();

        if ($options instanceof AgentOptions) {
            $agentOptions = $options;
        } elseif (is_array($options)) {
            $agentOptions = AgentOptions::fromArray($options);
        }

        $this->client = $client;
        $this->options = $agentOptions;
        $this->id = $id ?? 'agent_' . uniqid();
        $this->toolCacheManager = $toolCacheManager;

        // Level 4: Autonomous Agent config
        $this->mode = $agentOptions->get('mode') ?? null;
        $this->autonomyLevel = $agentOptions->get('autonomy_level') ?? null;
        $this->capabilities = $agentOptions->get('capabilities') ?? null;

        // Handle $instructions
        if ($instructions !== null ) {
            $agentOptions->setInstructions($instructions);
        }
    }

    /**
     * Get the OpenAI client instance.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get the agent's unique identifier.
     *
     * @return string|null The agent's ID
     */
    public function getId(): string|null
    {
        return $this->id;
    }

    /**
     * Set the agent's unique identifier.
     *
     * @param string $id The agent's ID
     * @return self Returns the Agent instance for method chaining
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the agent's message history.
     *
     * @return array The message history
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Set the agent's message history.
     *
     * @param array $messages The message history
     * @return self Returns the Agent instance for method chaining
     */
    public function setMessages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Get the agent's options.
     *
     * @return array|null The agent options
     */
    public function getOptions(): array|null
    {
        return $this->options->toArray();
    }

    /**
     * Get an individual option by key.
     *
     * @param string $key The option key
     * @return string|array|float|null The option value or null if not set
     */
    public function getOption(string $key): string|array|float|null
    {
        $agentOptions = $this->options;
        return $agentOptions->get($key) ?? null;
    }

    /**
     * Set individual option Model
     *
     * @param string $value The value to set
     */
    public function setModel(string $value): self
    {
        $this->options->setModel($value);
        return $this;
    }

    /**
     * Set individual option Temperature
     *
     * @param float $value The value to set
     */
    public function setTemperature(float $value): self
    {
        $this->options->setTemperature($value);
        return $this;
    }

    /**
     * Set individual option Top P
     *
     * @param float $value The value to set
     */
    public function setTopP(float $value): self
    {
        $this->options->setTopP($value);
        return $this;
    }

    /**
     * Set individual option Mode
     *
     * @param string $value The value to set
     */
    public function setMode(string $value): self
    {
        $this->options->setMode($value);
        return $this;
    }

    /**
     * Set individual option Autonomy Level
     *
     * @param string $value The value to set
     */
    public function setAutonomyLevel(string $value): self
    {
        $this->options->setAutonomyLevel($value);
        return $this;
    }

    /**
     * Set individual option Capabilities
     *
     * @param array $value The value to set
     */
    public function setCapabilities(array $value): self
    {
        $this->options->setCapabilities($value);
        return $this;
    }

    /**
     * Set individual option Tools
     *
     * @param array $value The value to set
     */
    public function setTools(array $value): self
    {
        $this->options->setTools($value);
        return $this;
    }

    /**
     * Set individual option Max Turns
     *
     * @param int $value The value to set
     */
    public function setMaxTurns(int $value): self
    {
        $this->options->setMaxTurns($value);
        return $this;
    }

    /**
     * Set individual option Max Input Tokens
     *
     * @param int $value The value to set
     */
    public function setMaxInputTokens(int $value): self
    {
        $this->options->setMaxInputTokens($value);
        return $this;
    }

    /**
     * Set individual option Max Conversation Tokens
     *
     * @param int $value The value to set
     */
    public function setMaxConversationTokens(int $value): self
    {
        $this->options->setMaxConversationTokens($value);
        return $this;
    }

    /**
     * Set individual option System Prompt
     *
     * @param string $value The value to set
     */
    public function setSystemPrompt(string $value): self
    {
        $this->options->setSystemPrompt($value);
        return $this;
    }

    /**
     * Set individual option Instructions
     *
     * @param string $value The value to set
     */
    public function setInstructions(string $value): self
    {
        $this->options->setInstructions($value);
        return $this;
    }

    /**
     * Get individual option Instructions
     *
     */
    public function getInstructions(): string
    {
        return $this->options->getInstructions() ?? '';
    }

    /**
     * Append instructions to the existing instructions.
     */
    public function appendInstructions(string $instructions): void
    {
        $this->options->appendInstructions($instructions);
    }

    /**
     * Set Handoff Target Permission.
     *
     * @param array $permission The permissions to set
     */
    public function setHandoffTargetPermission(array $permission): self
    {
        $this->options->handoff_target_permission = $permission;
        return $this;
    }

    /**
     * Allow all handoffs by setting the handoff target permission to all targets.
     *
     * @return self
     */
    public function allowAllHandoffs(): self
    {
        $this->options->handoff_target_permission = ['*'];
        return $this;
    }

    /**
     * Allow handoff from specified agents.
     *
     * @param string ...$agentNames Names of the agents allowed for handoff
     */
    public function allowHandoffFrom(string ...$agentNames): self
    {
        $this->options->handoff_target_permission = array_values($agentNames);
        return $this;
    }

    /**
     * Allow all except specified agents.
     *
     * @param string ...$blockedAgentNames List of agent names to be excluded
     * @return self
     */
    public function allowAllExcept(string ...$blockedAgentNames): self
    {
        $this->options->handoff_target_permission = ['*', 'blacklist' => array_values($blockedAgentNames)];
        return $this;
    }

    /**
     * Deny all handoff permissions by clearing the handoff target permissions array.
     *
     * @return self
     */
    public function denyAllHandoffs(): self
    {
        $this->options->handoff_target_permission = [];
        return $this;
    }

    /**
     * Get the agent's security permissions.
     *
     * @return array
     */
    public function getSecurityPermissions(): array
    {
        return $this->options->getSecurityPermissions();
    }


    /**
     * Set or update the system prompt for this agent.
     *
     * This method allows updating the agent's system prompt after initialization.
     * If a system message already exists, it will be replaced. If not, it will be added.
     *
     * @param string $systemPrompt The system prompt to set
     * @return self Returns the Agent instance for method chaining
     */
    public function updateConversationSystemPrompt(string $systemPrompt): self
    {
        // Check if a system message already exists
        foreach ($this->messages as $key => $message) {
            if ($message['role'] === 'system') {
                // Replace existing system message
                $this->messages[$key]['content'] = $systemPrompt;
                return $this;
            }
        }

        // No system message found, add as the first message
        array_unshift($this->messages, [
            'role' => 'system',
            'content' => $systemPrompt
        ]);

        return $this;
    }

    /**
     * Retrieves the content of the system prompt from the conversation messages.
     *
     * Iterates through the messages to find a message with the 'role' set to 'system'.
     * If found, returns its 'content'; otherwise, returns null.
     *
     * @return string|null The system prompt content or null if not found.
     */
    public function getConversationSystemPrompt(): string|null
    {
        foreach ($this->messages as $message) {
            if ($message['role'] === 'system') {
                return $message['content'];
            }
        }
        return null;
    }

    /**
     * Adds a developer message to the message collection.
     *
     * @param string $message The message content provided by the developer.
     * @return self
     */
    public function addDeveloperMessage(string $message): self
    {
        $this->messages[] = [
            'role' => 'developer',
            'content' => $message
        ];
        return $this;
    }

    /**
     * Get the conversation instructions.
     *
     * @return string
     */
    public function getConversationInstructions(): string
    {
        return $this->options->get('instructions') ?? '';
    }

    /**
     * Load context data into the agent.
     *
     * This method is used during handoff to transfer context from one agent to another.
     *
     * @param array $context The context data to load
     * @return self Returns the Agent instance for method chaining
     */
    public function loadContext(array $context): self
    {
        // Load messages if provided
        if (isset($context['messages']) && is_array($context['messages'])) {
            $this->messages = $context['messages'];
        }

        // Load other context data if needed
        // For example, you might want to set specific options or state based on the context

        return $this;
    }

    /**
     * Set the output type for structured responses.
     *
     * @param mixed $outputType The expected output type/schema
     * @return void
     */
    public function setOutputType(mixed $outputType): void
    {
        $this->outputType = $outputType;
    }

    /**
     * Get the output type for structured responses.
     *
     * @return mixed The expected output type/schema
     */
    public function getOutputType(): mixed
    {
        return $this->outputType;
    }

    /**
     * Get the total tokens used by the agent.*
     */
    public function getTokenUsage(): int
    {
        return $this->totalTokens;
    }

    /**
     * Add used tokens to the agent's total.
     */
    private function addTokenUsage(int $tokens): void
    {
        $this->totalTokens += $tokens;
    }

    /**
     * Get the RAG configuration.
     *
     * @return array|null
     */
    public function getRAGConfig(): array|null
    {
        return $this->ragConfig;
    }

    /**
     * Get the MCP manager.
     *
     * @return MCPManager|null
     */
    public function getMCPManager(): MCPManager|null
    {
        return $this->mcpManager;
    }




    // ========================================
    // PROGRESSIVE ENHANCEMENT - LEVEL 0 METHODS
    // ========================================

    /**
     * Simple chat method for Level 1 progressive enhancement.
     * Provides one-line usage for basic chat functionality.
     *
     * @param string $message The message to send
     * @param AgentOptions|array $options Optional configuration options
     * @return string The agent's response
     * @throws Exception
     */
    public static function simpleChat(string $message, AgentOptions|array $options = []): string
    {
        $manager = app(AgentManager::class);
        $agent = $manager->agent($options);
        return $agent->chat($message);
    }

    /**
     * Create a simple runner for Level 2 progressive enhancement.
     * Provides basic tool integration with minimal configuration.
     *
     * @param AgentOptions|array|null $options Optional configuration options
     * @param string|null $instructions
     * @param string|null $name
     * @return Runner A configured runner instance
     * @throws Exception
     */
    public static function runner(AgentOptions|array|null $options = null, string|null $instructions = null, string|null $name = null): Runner
    {
        $options ??= [];
        $manager = app(AgentManager::class);
        $agent = $manager->agent($options);
        $baseInstructions = $instructions ?? config('agents.multi_agent.default_runner.instructions');
        if ($baseInstructions) {
            $agent->setInstructions($baseInstructions);
        }
        $agentName = $name ?? config('agents.default_runner.name', 'runner_agent');
        return new Runner($agent, $agentName);
    }

    /**
     * Use a pre-configured agent for Level 2 progressive enhancement.
     * Provides declarative agent usage based on configuration.
     *
     * @param string $agentName The name of the pre-configured agent
     * @return Agent The configured agent instance
     * @throws Exception If the agent is not configured
     */
    public static function use(string $agentName): self
    {
        $config = config("agents.agents.{$agentName}");
        if (!$config) {
            throw new Exception("Agent '{$agentName}' not configured. Please add it to config/agents.php");
        }

        $manager = app(AgentManager::class);

        // Merge agent-specific options with defaults
        $options = array_merge(
            config('agents.default', []),
            $config['options'] ?? [],
            ['model' => $config['model'] ?? config('agents.default.model')]
        );

        // Create AgentOptions instance
        $agentOptions = AgentOptions::fromArray($options);
        $agent = $manager->agent($agentOptions, $config['system_prompt'] ?? null);

        // Auto-register tools if configured
        if (isset($config['tools']) && is_array($config['tools'])) {
            $runner = new Runner($agent);
            foreach ($config['tools'] as $toolName) {
                $runner->registerTool($toolName, self::getDefaultTool($toolName));
            }
        }

        return $agent;
    }

    /**
     * Create a new agent declaratively for Level 2 progressive enhancement.
     *
     * @param AgentOptions|array $config Agent configuration
     * @return Agent The configured agent instance
     */
    public static function create(AgentOptions|array $config): self
    {
        $manager = app(AgentManager::class);

        // If $config is already an AgentOptions instance, use it directly
        if ($config instanceof AgentOptions) {
            $agentOptions = $config;
            $systemPrompt = null; // AgentOptions already contains system_prompt if set
            $tools = null;
        } else {
            // Otherwise, create an AgentOptions instance from the array
            $options = array_merge(
                config('agents.default', []),
                $config['options'] ?? [],
                ['model' => $config['model'] ?? config('agents.default.model')]
            );

            // Level 4: Autonomous Agent config
            if (isset($config['mode'])) {
                $options['mode'] = $config['mode'];
            }
            if (isset($config['autonomy_level'])) {
                $options['autonomy_level'] = $config['autonomy_level'];
            }
            if (isset($config['capabilities'])) {
                $options['capabilities'] = $config['capabilities'];
            }

            $agentOptions = AgentOptions::fromArray($options);
            $systemPrompt = $config['system_prompt'] ?? null;
            $tools = $config['tools'] ?? null;
        }

        $agent = $manager->agent($agentOptions, $systemPrompt);

        // Auto-register tools if configured
        if ($tools !== null && is_array($tools)) {
            $runner = new Runner($agent);
            foreach ($tools as $toolName) {
                $runner->registerTool($toolName, self::getDefaultTool($toolName));
            }
        }

        return $agent;
    }

    /**
     * Get default tool implementations for auto-registration.
     *
     * @param string $toolName The tool name
     * @return callable The tool implementation
     */
    private static function getDefaultTool(string $toolName): callable
    {
        $defaultTools = [
            'echo' => fn($text) => $text,
            'date' => fn() => date('Y-m-d H:i:s'),
            'calculator' => function($args) {
                $expr = $args['expression'] ?? '0';
                try {
                    return eval("return {$expr};");
                } catch (Exception $e) {
                    return "Error calculating expression: {$expr}";
                }
            },
            'git' => function($args) {
                $command = $args['command'] ?? '--version';
                return shell_exec("git {$command}");
            },
            'docker' => function($args) {
                $command = $args['command'] ?? '--version';
                return shell_exec("docker {$command}");
            },
            'file_operations' => function($args) {
                $operation = $args['operation'] ?? 'read';
                $path = $args['path'] ?? '';

                switch ($operation) {
                    case 'read':
                        return file_exists($path) ? file_get_contents($path) : "File not found: {$path}";
                    case 'write':
                        $content = $args['content'] ?? '';
                        return file_put_contents($path, $content) ? "File written: {$path}" : "Error writing file: {$path}";
                    default:
                        return "Unknown operation: {$operation}";
                }
            },
            'statistics' => function($args) {
                $numbers = $args['numbers'] ?? [];
                if (empty($numbers)) return "No numbers provided";

                $count = count($numbers);
                $sum = array_sum($numbers);
                $avg = $sum / $count;
                $min = min($numbers);
                $max = max($numbers);

                return "Statistics: Count={$count}, Sum={$sum}, Avg={$avg}, Min={$min}, Max={$max}";
            },
            'chart_generator' => function($args) {
                $data = $args['data'] ?? [];
                $type = $args['type'] ?? 'bar';
                return "Generated {$type} chart with " . count($data) . " data points";
            },
            // RAG Tools
            'rag' => function($args) {
                $client = app(\OpenAI\Client::class);
                $ragTool = new \Sapiensly\OpenaiAgents\Tools\RAGTool($client);
                return $ragTool($args);
            },
            'vector_store' => function($args) {
                $client = app(\OpenAI\Client::class);
                $vectorStoreTool = new \Sapiensly\OpenaiAgents\Tools\VectorStoreTool($client);
                $action = $args['action'] ?? 'list';
                return $vectorStoreTool->$action($args);
            },
            'file_upload' => function($args) {
                $client = app(\OpenAI\Client::class);
                $fileUploadTool = new \Sapiensly\OpenaiAgents\Tools\FileUploadTool($client);
                $action = $args['action'] ?? 'list';
                return $fileUploadTool->$action($args);
            },
        ];

        return $defaultTools[$toolName] ?? fn($input) => "Tool {$toolName} not found";
    }

    /**
     * Search for a vector store by name.
     *
     * @param string $name
     * @return string|null
     */
    private function findVectorStoreByName(string $name): ?string
    {
        $result = $this->runTool('vector_store', 'list');
        $data = json_decode($result, true);

        if (!$data['success']) {
            return null;
        }

        foreach ($data['vector_stores'] as $vs) {
            if ($vs['name'] === $name) {
                return $vs['id'];
            }
        }

        return null;
    }

    /**
     * Search for a vector store by ID.
     */
    private function findVectorStoreById(string $id): ?string
    {
        $result = $this->runTool('vector_store', 'list');
        $data = json_decode($result, true);

        if (!$data['success']) {
            return null;
        }

        foreach ($data['vector_stores'] as $vs) {
            if ($vs['id'] === $id) {
                return $vs['id'];
            }
        }

        return null;
    }

    /**
     * Get a VectorStoreTool instance for this agent.
     * This method creates a new VectorStoreTool instance using the agent's OpenAI client account.
     * The VectorStoreTool can be used to interact with vector stores for RAG (Retrieval-Augmented Generation) tasks.
     *
     * @return VectorStoreTool
     */
    public function getVectorStoreTool(): VectorStoreTool
    {
        return new VectorStoreTool($this->client);
    }

    /**
     * Register official OpenAI tools (code_interpreter, retrieval, web_search)
     *
     * @param string $type Tool type: 'code_interpreter', 'retrieval', 'web_search'
     * @param array $config Tool-specific configuration. For 'code_interpreter', must include 'container' => 'cntr_...'.
     * @return self
     * @throws InvalidArgumentException if required config is missing
     */
    private function registerOpenAITools(string $type, array $config = []): self
    {
        $tool = match($type) {
            'code_interpreter' => [
                'type' => 'code_interpreter',
                'container' => isset($config['container']) && str_starts_with($config['container'], 'cntr')
                    ? $config['container']
                    : throw new InvalidArgumentException("You must provide a valid 'container' ID (starting with 'cntr') for code_interpreter. Example: ['container' => 'cntr_xxx...']"),
            ],
            'retrieval' => [
                'type' => 'retrieval',
                // You can add more required fields here if the API requires them
            ],
            'web_search' => [
                'type' => 'web_search',
                // You can add more required fields here if the API requires them
            ],
            'file_search' => [ //replacement for retrieval in Resposnes API
                'type' => 'file_search',
                // You can add more required fields here if the API requires them
            ],
            default => throw new InvalidArgumentException("Unknown OpenAI tool type: {$type}")
        };
        $this->openAITools[] = $tool;
        return $this;
    }

    public function registerCodeInterpreter(string $containerId): self
    {
        return $this->registerOpenAITools('code_interpreter', ['container' => $containerId]);
    }


    /**
     * Set the RAG tools for the agent.
     *
     * This method sets the tools required for RAG functionality, including
     * the vector store and file search tools.
     *
     * @return void
     * @throws Exception
     */
    private function registerRAG(): void
    {
        if(!$this->ragConfig) {
            throw new Exception("RAG configuration is not set. Please call useRAG() first.");
        }

        if (empty($this->ragConfig['vector_store_ids'])) {
            throw new Exception("Vector store IDs are required to register RAG tools.");
        }

        // Register the vector store tool
        $currentTools = $this->options->get('tools') ?? [];

        $this->setTools(array_merge($currentTools, [
            [
                'type' => 'file_search',
                'vector_store_ids' => $this->ragConfig['vector_store_ids'],
                'max_num_results' => $this->ragConfig['max_num_results'] ?? config('agent.rag.max_num_results', 5),
            ]
        ]));
    }

    /**
     * Registers and processes function schemas by validating and appending them
     * to the current tools configuration.
     *
     * This method generates function schemas, validates them, and integrates
     * valid schemas into the current tools configuration, if applicable.
     *
     * @param string|object|array|callable $functionSchema The input schema or configuration for the functions.
     * @throws ReflectionException
     */
    private function registerFunctionCalling(string|object|array|callable $functionSchema): void
    {
        if (empty($functionSchema)) {
            return;
        }
        $schemas = $this->generateFunctionSchema($functionSchema);
        if (empty($schemas)) {
            return;
        }

        $currentTools = $this->options->get('tools') ?? [];

        foreach ($schemas as $schema) {
            if ($this->isValidFunctionSchema($schema)) {
                $currentTools[] = $schema;
                // Register the function implementation if available
                if (isset($this->functionImplementations[$schema['name']])) {
                    $implementation = $this->functionImplementations[$schema['name']];
                    if (is_callable($implementation)) {
                        //$this->options->setFunctionImplementation($schema['name'], $implementation);
                    } else {
                        Log::warning("Function implementation for '{$schema['name']}' is not callable.");
                    }
                }
            }
        }

        $this->setTools($currentTools);

    }

    /**
     * Register Web Search Tool
     * This method registers or updates the configuration for the 'web_search_preview' tool.
     * It checks for the existence of an already configured tool, updates it if found,
     * or adds a new configuration if not. The modified tools are then saved.
     *
     * @param array $webSearchConfig Configuration details for the web search tool.
     * @return void
     */
    private function registerWebSearch(array $webSearchConfig): void
    {

        $currentTools = $this->options->get('tools') ?? [];

        $webSearchToolIndex = array_search('web_search_preview', array_column($currentTools, 'type'));

        if ($webSearchToolIndex !== false) {
            // Update existing tool configuration
            $currentTools[$webSearchToolIndex] = array_merge(
                ['type' => 'web_search_preview'],
                $webSearchConfig
            );
        } else {
            $currentTools[] = array_merge(
                ['type' => 'web_search_preview'],
                $webSearchConfig
            );
        }

        $this->setTools($currentTools);

        $this->useWebSearch = true;

    }

    /**
     * Register MCP tools.
     *
     * Accepts:
     * - MCPTool instance
     * - Array definition: ['server' => 'name', 'name' => 'tool', 'description' => ..., 'uri' => ..., 'parameters' => [...], 'schema' => [...]]
     * - Array of the above
     *
     * @param MCPTool|array|array[] $toolOrDef
     * @param string|null $serverName Optional server name if not provided inside definition
     * @param array $options Options for proxy tool creation (e.g., ['mode' => 'call'|'stream'])
     * @return self
     */
    public function registerMCPTool(MCPTool|array $toolOrDef, ?string $serverName = null, array $options = []): self
    {
        if (!$this->mcpManager) {
            $this->mcpManager = new MCPManager();
        }

        // If it's a list of items
        if (is_array($toolOrDef) && isset($toolOrDef[0]) && (is_array($toolOrDef[0]) || $toolOrDef[0] instanceof MCPTool)) {
            foreach ($toolOrDef as $item) {
                $this->registerMCPTool($item, $serverName, $options);
            }
            $this->useMCP = true;
            return $this;
        }

        if ($toolOrDef instanceof MCPTool) {
            $tool = $toolOrDef;
        } else {
            // Array definition
            $def = $toolOrDef;
            $srvName = $serverName ?? ($def['server'] ?? null);
            if (!$srvName) {
                throw new \InvalidArgumentException('Server name is required to register MCP tool definition.');
            }
            $server = $this->mcpManager->getServer($srvName);
            if (!$server) {
                throw new \InvalidArgumentException("Server '{$srvName}' not found for MCP tool registration.");
            }
            $tool = MCPTool::proxyFromDefinition($server, $def, $options);
        }

        $this->mcpManager->addTool($tool);

        // Register for OpenAI function-calling
        $this->registerFunctionCalling([$tool->getSchema()]);

        // Store the implementation
        $toolName = $tool->getName();
        $this->functionImplementations[$toolName] = function($params) use ($tool) {
            return $tool->execute($params);
        };

        $this->useMCP = true;
        return $this;
    }

    /**
     * Register an MCP server (legacy helper retained for BC).
     */
    public function registerMCPServer(string $name, string $url, array $config = []): self
    {
        if (!$this->mcpManager) {
            $this->mcpManager = new MCPManager();
        }
        $this->mcpManager->addServer($name, $url, $config);
        $this->useMCP = true;
        return $this;
    }

    /**
     * New: ergonomic registration for one or many MCP servers.
     * Accepts MCPServer instance, associative array, or list of those.
     */
    public function useMCPServer(MCP\MCPServer|array $serverOrList): self
    {
        if (!$this->mcpManager) {
            $this->mcpManager = new MCPManager();
        }

        // If it's a list
        if (is_array($serverOrList) && isset($serverOrList[0]) && (is_array($serverOrList[0]) || $serverOrList[0] instanceof MCP\MCPServer)) {
            foreach ($serverOrList as $item) {
                $this->useMCPServer($item);
            }
            $this->useMCP = true;
            return $this;
        }

        if ($serverOrList instanceof MCP\MCPServer) {
            $this->mcpManager->addServerInstance($serverOrList);
        } else {
            // associative array
            $name = $serverOrList['name'] ?? null;
            $url = $serverOrList['url'] ?? null;
            $config = $serverOrList['config'] ?? [];
            if (!$name || !$url) {
                throw new \InvalidArgumentException('Server array requires name and url keys.');
            }
            $this->mcpManager->addServer($name, $url, $config);
        }

        $this->useMCP = true;
        return $this;
    }

    /**
     * List registered MCP servers.
     *
     * @param bool $onlyEnabled If true, returns only enabled servers
     * @param bool $verbose If true, include extra details
     * @return array
     */
    public function listMCPServers(bool $onlyEnabled = false, bool $verbose = false): array
    {
        if (!$this->mcpManager) {
            return [];
        }

        $servers = $onlyEnabled
            ? $this->mcpManager->getEnabledServers()
            : $this->mcpManager->getServers();

        $out = [];
        /** @var MCPServer $srv */
        foreach ($servers as $name => $srv) {
            if ($verbose) {
                $out[$name] = [
                    'name' => $srv->getName(),
                    'url' => $srv->getUrl(),
                    'transport' => $srv->getTransport(),
                    'enabled' => $srv->isEnabled(),
                    'resources_count' => count($srv->getResources()),
                    'supports_sse' => $srv->supportsSSE(),
                ];
            } else {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * List MCP tools. Optionally, filter by one or more server names.
     *
     * @param string|array|null $serverNames 'calc' or ['calc','otro'] to filter. Null for all.
     * @param bool $onlyEnabled If true, include only enabled tools
     * @param bool $withSchema If true, include tool schemas/metadata; otherwise names only
     * @return array
     */
    public function listMCPTools(string|array|null $serverNames = null, bool|null $onlyEnabled = null, bool $withSchema = false): array
    {
        $onlyEnabled ??= false;
        if (!$this->mcpManager) {
            return [];
        }

        $tools = $onlyEnabled
            ? $this->mcpManager->getEnabledTools()
            : $this->mcpManager->getTools();

        // Normalize server filter
        $serverFilter = null;
        if (is_string($serverNames) && $serverNames !== '') {
            $serverFilter = [$serverNames];
        } elseif (is_array($serverNames) && !empty($serverNames)) {
            $serverFilter = array_values($serverNames);
        }

        $out = [];
        /** @var MCPTool $tool */
        foreach ($tools as $name => $tool) {
            $srvName = $tool->getServer()->getName();

            if ($serverFilter && !in_array($srvName, $serverFilter, true)) {
                continue;
            }

            if ($withSchema) {
                $out[$name] = [
                    'name' => $tool->getName(),
                    'server' => $srvName,
                    'resource' => $tool->getResource()->getName(),
                    'description' => $tool->getResource()->getDescription(),
                    'enabled' => $tool->isEnabled(),
                    'schema' => $tool->getSchema(),
                    'metadata' => $tool->getMetadata(),
                ];
            } else {
                $out[] = $name;
            }
        }

        return $out;
    }

    public function debugMCPServer(string $serverName, array $options = []): array
    {
        if (!$this->mcpManager) {
            return [ 'error' => 'MCP manager not initialized' ];
        }
        return $this->mcpManager->debugServer($serverName, $options);
    }

    /**
     * Exponer Tools (JSON-RPC) y Resources (REST) de un servidor MCP con filtros.
     *
     * - Si no pasas filtros/defaults, aplica inmediatamente (DX más simple)
     * - Puedes encadenar allow(), deny(), prefix(), sources(), mode(), apply()
     *
     * @param string $serverName
     * @param array $filters   ['allow'=>[], 'deny'=>[], 'prefix'=>'', 'sources'=>['tools','resources']]
     * @param array $defaults  ['mode'=>'auto'|'call'|'stream']
     * @return MCP\UniversalExposeBuilder
     * @throws \Exception
     */
    public function exposeMCP(string $serverName, array $filters = [], array $defaults = []): MCP\UniversalExposeBuilder
    {
        $builder = new MCP\UniversalExposeBuilder($this, $serverName);

        if (!empty($filters['allow']))   { $builder->allow($filters['allow']); }
        if (!empty($filters['deny']))    { $builder->deny($filters['deny']); }
        if (!empty($filters['prefix']))  { $builder->prefix($filters['prefix']); }
        if (!empty($filters['sources'])) { $builder->sources($filters['sources']); }
        if (!empty($defaults['mode']))   { $builder->mode($defaults['mode']); }

        if (empty($filters) && empty($defaults)) {
            $builder->apply(); // Aplica de inmediato si no hay chaining
        }
        return $builder;
    }

    /**
     * Alias explícito para exponer Tools y Resources con filtros/prefijo.
     * @throws Exception
     */
    public function exposeAllMCPToolsAndResources(string $serverName, array $filters = [], array $defaults = []): MCP\UniversalExposeBuilder
    {
        return $this->exposeMCP($serverName, $filters, $defaults);
    }

    /**
     * Use an existing RAG vector store by ID or name.
     *
     * @param array|string $vector_identifiers Vector store identifiers (ID or name) can be a single string or an array of strings.
     * @param int|null $max_num_results Maximum number of results to return
     * @return self
     * @throws Exception
     */
    public function useRAG(array|string $vector_identifiers, int|null $max_num_results = null): self
    {
        // $vector_identifiers cannot be null or empty string o empty array
        if (empty($vector_identifiers)) {
            throw new Exception("Vector identifiers cannot be empty.");
        }

        // Check if $vector_identifier is string or array
        if (is_array($vector_identifiers)) {
            $vector_identifiers_array = $vector_identifiers; // Use the first identifier
        } else {
            $vector_identifiers_array = [$vector_identifiers];
        }

        foreach ($vector_identifiers_array as $vector_identifier) {
            // If it's an ID, use it directly
            if (str_starts_with($vector_identifier, 'vs_')) {
                $vectorStoreId = $this->findVectorStoreById($vector_identifier);
            }else{
                // Else probably it's a name, search for it in the list
                $vectorStoreId = $this->findVectorStoreByName($vector_identifier);
            }
            if (!$vectorStoreId) {
                throw new Exception("Vector store '{$vector_identifier}' not found.");
            }
            $vectorStoreIds[] = $vectorStoreId;
        }

        // if $vectorStoreIds is empty, throw an exception
        if (empty($vectorStoreIds)) {
            throw new Exception("No valid vector store IDs found for the provided identifiers.");
        }

        $max_num_results = $max_num_results ?? config('agent.rag.max_num_results', 5);
        $this->ragConfig = array_merge((array)$this->ragConfig, [
            'type' => 'file_search',
            'vector_store_ids' => $vectorStoreIds,
            'max_num_results' => $max_num_results,
        ]);

        $this->registerRAG();

        return $this;
    }

    /**
     * Register and use functions for OpenAI interactions.
     * This method manages the registration of function schemas and their implementations.
     * It ensures the proper setup of the given functions to be utilized with OpenAI's API.
     *
     * @param string|object|array|callable $functions Functions to be registered and used.
     * @return self
     * @throws Exception
     */
    public function useFunctions(string|object|array|callable $functions): self
    {
        // First, register the function schemas
        $schemas = $this->generateFunctionSchema($functions);

        // Register schemas with OpenAI
        $this->registerFunctionCalling($schemas);

        // Handle implementation registration based on input type
        $this->registerImplementations($functions, $schemas);


        return $this;
    }

    /**
     * Execute an MCP tool.
     *
     * @param string $toolName The tool name
     * @param array $parameters The tool parameters
     * @return mixed
     * @throws Exception If MCP manager is not initialized
     */
    public function executeMCPTool(string $toolName, array $parameters = []): mixed
    {
        if (!$this->mcpManager) {
            throw new Exception('MCP manager not initialized');
        }

        return $this->mcpManager->executeTool($toolName, $parameters);
    }

    /**
     * Stream a resource from an MCP server.
     *
     * @param string $serverName The server name
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @return iterable
     * @throws Exception If MCP manager is not initialized
     */
    public function streamMCPResource(string $serverName, string $resourceName, array $parameters = []): iterable
    {
        if (!$this->mcpManager) {
            throw new Exception('MCP Manager not initialized');
        }

        return $this->mcpManager->streamResource($serverName, $resourceName, $parameters);
    }

    /**
     * Configure and use web search.
     * This method sets up the web search configuration based on the provided context size
     * and location details. It validates the search context size and constructs the search
     * parameters accordingly.
     *
     * @param string|null $search_context_size The desired level of search context: 'high', 'medium', or 'low'. Defaults to 'medium'.
     * @param string|null $country The user's country for location-specific search, if provided.
     * @param string|null $city The user's city for location-specific search, if provided.
     * @return self
     * @throws InvalidArgumentException If an invalid search_context_size is provided.
     */
    public function useWebSearch(string|null $search_context_size = null, string|null $country = null, string|null $city = null): self
    {
        $validContextSizes = ['high', 'medium', 'low'];
        $contextSize = $search_context_size ?? 'medium';

        if (!in_array($contextSize, $validContextSizes, true)) {
            throw new InvalidArgumentException(
                "Invalid search_context_size: '{$contextSize}'. Valid options: " . implode(', ', $validContextSizes)
            );
        }

        // Build a configuration array
        $webSearchConfig = [];

        // Only add context size if it's not the default 'medium'
        if ($contextSize !== 'medium') {
            $webSearchConfig['search_context_size'] = $contextSize;
        }

        // Add location configuration if provided
        if ($country !== null || $city !== null) {
            $webSearchConfig['user_location']['type'] = "approximate";
            if ($country !== null) { $webSearchConfig['user_location']['country'] = $country; }
            if ($city !== null) { $webSearchConfig['user_location']['city'] = $city; }
        }

        $this->registerWebSearch($webSearchConfig);

        return $this;
    }

    /**
     * Register function implementations based on input type
     */
    private function registerImplementations(string|object|array|callable $input, array $schemas): void
    {
        if (is_string($input) && class_exists($input)) {
            // Class string - instantiate and register methods
            $instance = new $input();
            $this->registerClassImplementations($instance, $schemas);

        } elseif (is_object($input)) {
            // Object instance - register methods
            $this->registerClassImplementations($input, $schemas);

        } elseif (is_array($input)) {
            // Array can be either schemas or mixed functions
            $this->registerArrayImplementations($input);

        } elseif (is_callable($input)) {
            // Single callable
            $this->registerCallableImplementation($input, $schemas);
        }
    }

    /**
     * Call method with argument mapping
     * @throws ReflectionException
     */
    private function callMethodWithArgs(object $instance, string $methodName, array $args): mixed
    {
        $method = new \ReflectionMethod($instance, $methodName);
        $parameters = $method->getParameters();
        $orderedArgs = [];

        foreach ($parameters as $param) {
            $paramName = $param->getName();

            if (isset($args[$paramName])) {
                $orderedArgs[] = $args[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $orderedArgs[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $orderedArgs[] = null;
            } else {
                throw new InvalidArgumentException("Missing required parameter: {$paramName}");
            }
        }

        return $method->invokeArgs($instance, $orderedArgs);
    }

    /**
     * Register implementations from class instance
     */
    private function registerClassImplementations(object $instance, array $schemas): void
    {
        foreach ($schemas as $schema) {
            $functionName = $schema['name'];
            $camelCaseMethodName = $this->snakeToCamel($functionName);

            // Check for both camelCase and snake_case method names
            if (method_exists($instance, $camelCaseMethodName)) {
                $this->functionImplementations[$functionName] = function($args) use ($instance, $camelCaseMethodName) {
                    return $this->callMethodWithArgs($instance, $camelCaseMethodName, $args);
                };
            } elseif (method_exists($instance, $functionName)) {
                // Support for methods that are already in snake_case (like in WeatherService)
                $this->functionImplementations[$functionName] = function($args) use ($instance, $functionName) {
                    return $this->callMethodWithArgs($instance, $functionName, $args);
                };
            }
        }
    }

    /**
     * Get callable name for registration
     */
    private function getCallableName(callable $function): string
    {
        if (is_string($function)) {
            return $this->camelToSnake($function);
        }

        if (is_array($function)) {
            $className = is_object($function[0]) ? get_class($function[0]) : $function[0];
            return $this->camelToSnake($className . '_' . $function[1]);
        }

        return 'anonymous_function_' . uniqid();
    }

    /**
     * Register implementations from array input
     */
    private function registerArrayImplementations(array $input): void
    {
        foreach ($input as $item) {
            if (is_callable($item)) {
                // Simple callable in array
                $name = $this->getCallableName($item);
                $this->functionImplementations[$name] = $item;

            } elseif (is_array($item) && isset($item['name'], $item['implementation'])) {
                // Structured function definition
                $this->functionImplementations[$item['name']] = $item['implementation'];
            }
        }
    }

    /**
     * Register implementation from single callable
     */
    private function registerCallableImplementation(callable $callable, array $schemas): void
    {
        if (!empty($schemas)) {
            $functionName = $schemas[0]['name'];
            $this->functionImplementations[$functionName] = $callable;
        }
    }


    /**
     * Run a tool with the given arguments.
     *
     * @param string $toolName The tool name
     * @param string $action The action to perform
     * @param array $args The arguments
     * @return string The tool result
     */
    private function runTool(string $toolName, string $action, array $args = []): string
    {
        $tool = self::getDefaultTool($toolName);
        $args['action'] = $action;
        return $tool($args);
    }

    /**
     * Process a chat message and return the AI response.
     *
     * This method handles the conversation flow, including tool definitions
     * for function calling when provided. It manages the message history
     * and constructs the appropriate API call to OpenAI.
     *
     * @param string $message The user message to process
     * @param array|null $toolDefinitions Array of tool definitions for function calling
     * @param mixed|null $outputType Optional output type override
     * @return string The AI's response
     * @throws Exception
     */
    public function chat(string $message, array|null $toolDefinitions = null, mixed $outputType = null): string
    {
        $toolDefinitions ??= [];

        $this->messages[] = ['role' => 'user', 'content' => $message];

        return $this->chatWithResponsesAPI($message, $toolDefinitions, $outputType);
    }

    /**
     * Chat using the Responses API
     * This method handles the chat interaction with the OpenAI Responses API.
     * It prepares the conversation history, manages token limits,
     * and constructs the API request to get a response.
     *
     *
     * @param string|null $message
     * @param array $toolDefinitions
     * @param mixed|null $outputType
     * @return string
     * @throws Exception
     */
    private function chatWithResponsesAPI(string|null $message = null, array $toolDefinitions = [], mixed $outputType = null): string
    {
        //Log::info("[Agent Debug] Chat with chatWithResponsesAPI message: {$message}");
        $startTime = microtime(true);

        // Set max turns memory from options or default to 10
        $maxTurns = $this->options->get('max_turns') ?? config('agent.default.max_turns', 10);
        // Set max input tokens from options or default to 4096
        $maxInputTokens = $this->options->get('max_input_tokens') ?? config('agent.default.max_turns', 4096);

        // Get the limited message history (last N turns + system prompt)
        $limitedMessages = $this->getLimitedMessages($maxTurns, $maxInputTokens);

        $inputItems = [];
        foreach ($limitedMessages as $msg) {
            $inputItems[] = [
                'role' => $msg['role'],  // 'user', 'assistant', 'developer'
                'content' => $this->buildContentBlocks($msg),
            ];
        }

        // New user message
        if( !empty($message)) {
            $current = ['content' => $message];
            // TODO: Add support for images and audio URLs
            if (!empty($imageUrl)) $current['image_url'] = $imageUrl;
            if (!empty($audioUrl)) $current['audio_url'] = $audioUrl;

            $inputItems[] = [
                'role' => 'user',
                'content' =>  $this->buildContentBlocks(['role' => 'user'] + $current),
            ];
        }


        // Set conversation token limit
        $inputTokenCount = 0;
        $conversationTokenLimit = $this->options->get('max_conversation_tokens') ?? config('agent.default.max_conversation_tokens', 10000);
        foreach ($inputItems as $item) {
            // Estimate token count for each item
            if (isset($item['content']) && $item['content'][0]['type'] === 'input_text') {
                $itemTokenCount = (int)(strlen($item['content'][0]['text']) / 4); // Estimate of token count
                $inputTokenCount += $itemTokenCount;
            }
        }

        $totalUsed = $this->getTokenUsage();
        if ($totalUsed + $inputTokenCount >= $conversationTokenLimit) {
            throw new \RuntimeException("Limit reached for this conversation. Start a new conversation.");
        }

        // Tools Management
        // 1. Merge tools from options and MCP if active
        $mergedToolDefs = [];

        // a) Tools already set in options (if any)
        $existingTools = $this->options->get('tools') ?? [];
        if (!empty($existingTools)) {
            // Se asume ya en formato OpenAI (por compatibilidad con otras features)
            $mergedToolDefs = array_merge($mergedToolDefs, $existingTools);
        }

        // b) Tools from provided definitions
        if (!empty($toolDefinitions)) {
            foreach ($toolDefinitions as $tool) {
                $mergedToolDefs[] = $this->normalizeToolForOpenAI($tool);
            }
        }

        // c) Include MCP tools if enabled
        if ($this->useMCP && $this->mcpManager) {
            $mcpTools = $this->mcpManager->getToolDefinitions(); // schemas MCP ya registrados
            foreach ($mcpTools as $tool) {
                $mergedToolDefs[] = $this->normalizeToolForOpenAI($tool);
            }
        }

        // d) Remove duplicates by name (the last one wins)
        $seen = [];
        foreach ($mergedToolDefs as $raw) {
            // Detectar nombre desde distintos formatos
            $toolName = $raw['name'] ?? ($raw['function']['name'] ?? null);
            if (!$toolName) {
                continue;
            }

            // Normalizar a formato Responses API: { type: 'function', name, parameters, description? }
            $normalized = $this->normalizeToolForOpenAI($raw);
            $seen[$toolName] = $normalized; // sobreescribe duplicados
        }
        $finalTools = array_values($seen);



        // Prepare the parameters for the Responses API
        $params = [
            'model' => $this->options->get('model') ?? 'gpt-4o',
            'instructions' => $this->options->get('instructions') ?? '', // instructions replaces system prompt in Responses API
            'input' => $inputItems,
            'temperature' => $this->options->get('temperature') ?? null,
            'top_p' => $this->options->get('top_p') ?? null,
            //'tools' => $this->options->get('tools') ?? []
            //'modalities' => ['text', 'audio'], // request both formats (optional)
        ];

        if (!empty($finalTools)) {
            $params['tools'] = $finalTools;
            $params['tool_choice'] = 'auto';
        }

        // Handle output type for structured responses
        $outputType = $outputType ?? $this->outputType;
        if ($outputType !== null) {
            $params['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->client->responses()->create($params);
            $endTime = microtime(true);
            //dump("[Agent Debug] Responses API response: " . json_encode($response, JSON_PRETTY_PRINT));

            // Extract content from Responses API response
            $response_content = '';
            $role = $response->output[0]->role ?? 'assistant';

            // Check if the response has outputs and process them
            if ($response->output) {
                foreach ($response->output as $output) {
                    // Check if the output is function_call
                    if (isset($output->type) && $output->type === 'function_call') {
                        //dump("[Agent Debug] Function call detected: " . json_encode($output, JSON_PRETTY_PRINT));
                        // Process function call output
                        $functionName = $output->name ?? 'unknown_function';
                        $arguments = $output->arguments ? json_decode($output->arguments, true) : [];
                        //dump("[Agent Debug] Function call arguments: " . json_encode($arguments, JSON_PRETTY_PRINT));
                        // Execute the function call
                        $toolResult = $this->executeFunction($functionName, $arguments);
                        //dump( "[Agent Debug] Function Tool Result:". json_encode($toolResult, JSON_PRETTY_PRINT));
                        // Add the tool result to messages
                        $this->messages[] = [
                            'role' => 'developer',
                            'tool_call_id' => $output->id,
                            'name' => $functionName,
                            'arguments' => $output->arguments,
                            'content' => 'Tool call executed: ' . $functionName. ' with arguments: ' . json_encode($arguments, JSON_PRETTY_PRINT) .
                                ' and result: ' . json_encode($toolResult, JSON_PRETTY_PRINT)
                        ];

                        // Run the response again to get the final answer
                        $finalResponse = $this->chatWithResponsesAPI();
                        //dump("[Agent Debug] Final response after function call: " . $finalResponse);
                        $response_content .= $finalResponse;


                        continue; // Skip to next output if function call was processed
                    }

                    // Check if the output has content
                    if (!isset($output->content) || !is_array($output->content)) {
                        continue; // Skip if no content
                    }
                    foreach ($output->content as $content) {
                        if (isset($content->type) && $content->type === 'output_text') {
                            $response_content .=  $content->text ?? '';
                        }
                    }
                }
            }

            // Calculate token usage
            $totalTokenCount = isset($response->usage) ? ($response->usage->outputTokens ?? 0) : 0;
            $this->addTokenUsage($totalTokenCount);

            if (!empty($response_content)) {
                $this->messages[] = ['role' => $role, 'content' => $response_content];
            }

            // EMIT THE EVENT WITH MESSAGE AND SOME ANALYTICS DATA
            $this->fireResponseEvent($message, $response_content, $startTime, $endTime, $response, $this->getTokenUsage() , 'Responses API');

            return $response_content;

        } catch (Exception $e) {
            //Log::error("[Agent Debug] Chat With Responses API error: {$e->getMessage()}");

            // EMIT ERROR EVENT
            $this->fireErrorEvent($message, $e);

            throw $e;

        }
    }

    /**
     * Chat using the Chat Completions API (for function tools)
     */
    private function chatWithChatAPI(string $message, array $toolDefinitions, mixed $outputType): string
    {
        Log::info("[Agent Debug] Chat with chatWithChatAPI: {$message}");

        $params = [
            'model' => $this->options->get('model') ?? 'gpt-4o',
            'messages' => $this->messages,
            'temperature' => $this->options->get('temperature') ?? null,
            'top_p' => $this->options->get('top_p') ?? null,
        ];

        // Add function tools
        if (!empty($toolDefinitions)) {
            $tools = [];
            foreach ($toolDefinitions as $tool) {
                if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                    $tools[] = $tool;
                } else {
                    $parameters = ['type' => 'object', 'properties' => new \stdClass(), 'required' => []];
                    if (isset($tool['schema'])) {
                        if (isset($tool['schema']['schema'])) {
                            $parameters = $tool['schema']['schema'];
                        } elseif (isset($tool['schema']['type'])) {
                            $parameters = $tool['schema'];
                        }
                    }
                    if (isset($parameters['properties']) && is_array($parameters['properties']) && empty($parameters['properties'])) {
                        $parameters['properties'] = new \stdClass();
                    }
                    $openaiTool = [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool['name'] ?? 'unknown_function',
                            'parameters' => $parameters,
                        ]
                    ];
                    if (isset($tool['description'])) {
                        $openaiTool['function']['description'] = $tool['description'];
                    } elseif (isset($tool['schema']['description'])) {
                        $openaiTool['function']['description'] = $tool['schema']['description'];
                    }
                    $tools[] = $openaiTool;
                }
            }
            $params['tools'] = $tools;
            $params['tool_choice'] = 'auto';
        }

        // Handle output type for structured responses
        $outputType = $outputType ?? $this->outputType;
        if ($outputType !== null) {
            $params['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->client->chat()->create($params);

            $choice = $response['choices'][0];
            $message = $choice['message'];
            $finishReason = $choice['finishReason'] ?? $choice['finish_reason'] ?? 'unknown';
            $content = $message['content'] ?? '';

            // ✅ Buscar tool_calls en múltiples ubicaciones y formatos
            $toolCalls = $this->extractToolCalls($response, $message, $finishReason);

            if (!empty($toolCalls)) {
                // Agregar el mensaje del asistente al historial
                $this->messages[] = ['role' => 'assistant', 'content' => $content, 'tool_calls' => $toolCalls];

                // Procesar cada tool call
                foreach ($toolCalls as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $arguments = $toolCall['function']['arguments'];

                    // ✅ Ejecutar la función real registrada
                    $toolResult = $this->executeToolCall($functionName, $arguments, $toolDefinitions);

                    $this->messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'content' => $toolResult
                    ];
                }

                // Hacer la segunda llamada para obtener la respuesta final
                $finalParams = [
                    'model' => $this->options->get('model') ?? 'gpt-4o',
                    'messages' => $this->messages,
                    'temperature' => $this->options->get('temperature') ?? null,
                    'top_p' => $this->options->get('top_p') ?? null,
                ];

                $finalResponse = $this->client->chat()->create($finalParams);
                $content = $finalResponse['choices'][0]['message']['content'] ?? '';

            } elseif ($finishReason === 'tool_calls') {
                Log::error("[Agent Debug] OpenAI indicated tool_calls but no tool_calls data found");
                return "Error: OpenAI wanted to call a function but the call data was not found.";
            }

            if (!empty($content)) {
                $this->messages[] = ['role' => 'assistant', 'content' => $content];
            }

            return $content;

        } catch (Exception $e) {
            Log::error("[Agent Debug] Chat API error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Process a chat message and stream the AI response in real-time.
     *
     * This method handles the conversation flow with streaming, including tool definitions
     * for function calling when provided. It yields response chunks as they become available.
     *
     * @param string $message The user message to process
     * @param array|null $toolDefinitions Array of tool definitions for function calling
     * @param mixed|null $outputType Optional output type override
     * @return iterable<string> Stream of response chunks
     * @throws Exception
     */
    public function chatStreamed(string $message, array|null $toolDefinitions = null, mixed $outputType = null): iterable
    {
        Log::info("[Agent Debug] Chat with chatStreamed: {$message}");

        $toolDefinitions ??= [];

        $this->messages[] = ['role' => 'user', 'content' => $message];

        // Check if we have RAG configuration
        if ($this->ragConfig !== null) {
            yield from $this->chatStreamedWithRetrieval($message, $toolDefinitions, $outputType);
            return;
        }

        // Check if we have OpenAI official tools registered
        $hasOpenAITools = !empty($this->openAITools);

        if ($hasOpenAITools) {
            // For now, Responses API doesn't support streaming with official tools
            // So we'll use the non-streaming version
            $response = $this->chatWithResponsesAPI($message, $toolDefinitions, $outputType);
            yield $response;
            return;
        } else {
            // Use Chat Completions API for function tools with streaming
            yield from $this->chatStreamedWithChatAPI($message, $toolDefinitions, $outputType);
        }
    }

    /**
     * Stream chat with retrieval tool enabled.
     *
     * @param string $message The user message to process
     * @param array $toolDefinitions Array of tool definitions for function calling
     * @param mixed $outputType Optional output type override
     * @return iterable The streaming response
     * @throws Exception
     */
    private function chatStreamedWithRetrieval(string $message, array $toolDefinitions, mixed $outputType): iterable
    {
        Log::info("[Agent Debug] Chat with chatStreamedWithRetrieval: {$message}");

        if (!empty($toolDefinitions)) {
            throw new Exception('No se pueden usar tools de tipo function junto con retrieval en la misma llamada.');
        }

        // Intentar usar el tool de retrieval oficial de OpenAI con streaming
        try {
            $params = [
                'model' => $this->options->get('model') ?? 'gpt-4o',
                'messages' => $this->messages,
                'temperature' => $this->options->get('temperature') ?? null,
                'top_p' => $this->options->get('top_p') ?? null,
                'stream' => true,
                'tools' => [
                    [
                        'type' => 'retrieval',
                        'retrieval' => [
                            'vector_store_id' => $this->ragConfig['vector_store_id'],
                            'k' => $this->ragConfig['k'],
                            'r' => $this->ragConfig['r']
                        ]
                    ]
                ],
                'tool_choice' => 'auto',
            ];

            if ($outputType !== null) {
                $params['response_format'] = ['type' => 'json_object'];
            }

            $stream = $this->client->chat()->createStreamed($params);
            $fullContent = '';
            $toolCalls = [];

            foreach ($stream as $response) {
                $choice = $response['choices'][0];
                $delta = $choice['delta'] ?? [];
                $finishReason = $choice['finishReason'] ?? $choice['finish_reason'] ?? null;

                // Handle content streaming
                if (isset($delta['content'])) {
                    $content = $delta['content'];
                    $fullContent .= $content;
                    yield $content;
                }

                // Handle tool calls streaming
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $toolCallDelta) {
                        if (isset($toolCallDelta['index'])) {
                            $index = $toolCallDelta['index'];

                            if (!isset($toolCalls[$index])) {
                                $toolCalls[$index] = [
                                    'id' => '',
                                    'type' => 'function',
                                    'function' => ['name' => '', 'arguments' => '']
                                ];
                            }

                            if (isset($toolCallDelta['id'])) {
                                $toolCalls[$index]['id'] = $toolCallDelta['id'];
                            }

                            if (isset($toolCallDelta['function']['name'])) {
                                $toolCalls[$index]['function']['name'] = $toolCallDelta['function']['name'];
                            }

                            if (isset($toolCallDelta['function']['arguments'])) {
                                $toolCalls[$index]['function']['arguments'] .= $toolCallDelta['function']['arguments'];
                            }
                        }
                    }
                }

                // If finished with tool calls, execute them
                if ($finishReason === 'tool_calls' && !empty($toolCalls)) {
                    // Add assistant message with tool calls
                    $this->messages[] = ['role' => 'assistant', 'content' => $fullContent, 'tool_calls' => $toolCalls];

                    // Execute tool calls
                    foreach ($toolCalls as $toolCall) {
                        $functionName = $toolCall['function']['name'];
                        $arguments = $toolCall['function']['arguments'];

                        $toolResult = $this->executeToolCall($functionName, $arguments, $toolDefinitions);

                        $this->messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'content' => $toolResult
                        ];
                    }

                    // Make second call for final response
                    $finalParams = [
                        'model' => $this->options->get('model') ?? 'gpt-4o',
                        'messages' => $this->messages,
                        'temperature' => $this->options->get('temperature') ?? null,
                        'top_p' => $this->options->get('top_p') ?? null,
                        'stream' => true,
                    ];

                    $finalStream = $this->client->chat()->createStreamed($finalParams);

                    foreach ($finalStream as $finalResponse) {
                        $finalChoice = $finalResponse['choices'][0];
                        $finalDelta = $finalChoice['delta'] ?? [];

                        if (isset($finalDelta['content'])) {
                            $content = $finalDelta['content'];
                            yield $content;
                        }
                    }
                }
            }

            // Add final message to history if not already added
            if (!empty($fullContent) && $finishReason !== 'tool_calls') {
                $this->messages[] = ['role' => 'assistant', 'content' => $fullContent];
            }

        } catch (Exception $e) {
            // Fallback a RAG tradicional en streaming
            if (strpos($e->getMessage(), "tools[0].function") !== false || strpos($e->getMessage(), "Missing required parameter") !== false) {
                $results = $this->runTool('vector_store', 'search', [
                    'vector_store_id' => $this->ragConfig['vector_store_id'],
                    'query' => $message,
                    'k' => $this->ragConfig['k'],
                    'r' => $this->ragConfig['r']
                ]);
                $data = json_decode($results, true);
                $context = '';
                if (isset($data['success']) && $data['success'] && isset($data['results'])) {
                    foreach ($data['results'] as $i => $doc) {
                        $text = $doc['text'] ?? '';
                        // If it's an array of objects with 'text', concatenate the texts
                        if (is_array($text)) {
                            // If it's an array of objects with 'text'
                            if (isset($text[0]['text'])) {
                                $text = implode("\n\n", array_map(fn($t) => $t['text'] ?? '', $text));
                            } else {
                                $text = implode("\n", $text);
                            }
                        }
                        $context .= "[Doc #" . ($i+1) . "]\n" . $text . "\n";
                    }
                }
                $contextMsg = "CONTEXT:\n" . $context . "\n---\n";
                $this->messages[] = ['role' => 'system', 'content' => $contextMsg];
                $this->messages[] = ['role' => 'user', 'content' => $message];
                $params = [
                    'model' => $this->options->get('model') ?? 'gpt-4o',
                    'messages' => $this->messages,
                    'temperature' => $this->options->get('temperature') ?? null,
                    'top_p' => $this->options->get('top_p') ?? null,
                    'stream' => true,
                ];
                if ($outputType !== null) {
                    $params['response_format'] = ['type' => 'json_object'];
                }
                $stream = $this->client->chat()->createStreamed($params);
                foreach ($stream as $response) {
                    $choice = $response['choices'][0];
                    $delta = $choice['delta'] ?? [];
                    if (isset($delta['content'])) {
                        yield $delta['content'];
                    }
                }
            } else {
                throw $e;
            }
        }
        return;
    }

    /**
     * Stream chat using the Chat Completions API (for function tools)
     */
    private function chatStreamedWithChatAPI(string $message, array $toolDefinitions, mixed $outputType): iterable
    {
        Log::info("[Agent Debug] Chat with chatStreamedWithRetrieval: {$message}");

        $params = [
            'model' => $this->options->get('model') ?? 'gpt-4o',
            'messages' => $this->messages,
            'temperature' => $this->options->get('temperature') ?? null,
            'top_p' => $this->options->get('top_p') ?? null,
            'stream' => true, // Enable streaming
        ];

        // Add function tools
        if (!empty($toolDefinitions)) {
            $tools = [];
            foreach ($toolDefinitions as $tool) {
                if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                    $tools[] = $tool;
                } else {
                    $parameters = ['type' => 'object', 'properties' => new \stdClass(), 'required' => []];
                    if (isset($tool['schema'])) {
                        if (isset($tool['schema']['schema'])) {
                            $parameters = $tool['schema']['schema'];
                        } elseif (isset($tool['schema']['type'])) {
                            $parameters = $tool['schema'];
                        }
                    }
                    if (isset($parameters['properties']) && is_array($parameters['properties']) && empty($parameters['properties'])) {
                        $parameters['properties'] = new \stdClass();
                    }
                    $openaiTool = [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool['name'] ?? 'unknown_function',
                            'parameters' => $parameters,
                        ]
                    ];
                    if (isset($tool['description'])) {
                        $openaiTool['function']['description'] = $tool['description'];
                    } elseif (isset($tool['schema']['description'])) {
                        $openaiTool['function']['description'] = $tool['description'];
                    }
                    $tools[] = $openaiTool;
                }
            }
            $params['tools'] = $tools;
            $params['tool_choice'] = 'auto';
        }

        // Handle output type for structured responses
        $outputType = $outputType ?? $this->outputType;
        if ($outputType !== null) {
            $params['response_format'] = ['type' => 'json_object'];
        }

        try {
            $stream = $this->client->chat()->createStreamed($params);
            $fullContent = '';
            $toolCalls = [];

            foreach ($stream as $response) {
                $choice = $response['choices'][0];
                $delta = $choice['delta'] ?? [];
                $finishReason = $choice['finishReason'] ?? $choice['finish_reason'] ?? null;

                // Handle content streaming
                if (isset($delta['content'])) {
                    $content = $delta['content'];
                    $fullContent .= $content;
                    yield $content;
                }

                // Handle tool calls streaming
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $toolCallDelta) {
                        if (isset($toolCallDelta['index'])) {
                            $index = $toolCallDelta['index'];

                            if (!isset($toolCalls[$index])) {
                                $toolCalls[$index] = [
                                    'id' => '',
                                    'type' => 'function',
                                    'function' => ['name' => '', 'arguments' => '']
                                ];
                            }

                            if (isset($toolCallDelta['id'])) {
                                $toolCalls[$index]['id'] = $toolCallDelta['id'];
                            }

                            if (isset($toolCallDelta['function']['name'])) {
                                $toolCalls[$index]['function']['name'] = $toolCallDelta['function']['name'];
                            }

                            if (isset($toolCallDelta['function']['arguments'])) {
                                $toolCalls[$index]['function']['arguments'] .= $toolCallDelta['function']['arguments'];
                            }
                        }
                    }
                }

                // If finished with tool calls, execute them
                if ($finishReason === 'tool_calls' && !empty($toolCalls)) {
                    // Add assistant message with tool calls
                    $this->messages[] = ['role' => 'assistant', 'content' => $fullContent, 'tool_calls' => $toolCalls];

                    // Execute tool calls
                    foreach ($toolCalls as $toolCall) {
                        $functionName = $toolCall['function']['name'];
                        $arguments = $toolCall['function']['arguments'];

                        $toolResult = $this->executeToolCall($functionName, $arguments, $toolDefinitions);

                        $this->messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'content' => $toolResult
                        ];
                    }

                    // Make second call for final response
                    $finalParams = [
                        'model' => $this->options->get('model') ?? 'gpt-4o',
                        'messages' => $this->messages,
                        'temperature' => $this->options->get('temperature') ?? null,
                        'top_p' => $this->options->get('top_p') ?? null,
                        'stream' => true,
                    ];

                    $finalStream = $this->client->chat()->createStreamed($finalParams);

                    foreach ($finalStream as $finalResponse) {
                        $finalChoice = $finalResponse['choices'][0];
                        $finalDelta = $finalChoice['delta'] ?? [];

                        if (isset($finalDelta['content'])) {
                            $content = $finalDelta['content'];
                            yield $content;
                        }
                    }
                }
            }

            // Add final message to history if not already added
            if (!empty($fullContent) && $finishReason !== 'tool_calls') {
                $this->messages[] = ['role' => 'assistant', 'content' => $fullContent];
            }

        } catch (Exception $e) {
            Log::error("[Agent Debug] Chat streaming API error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Generate speech from text using OpenAI's Text-to-Speech API.
     *
     * @param string $text The text to convert to speech
     * @param array $options Optional parameters for the speech API
     * @return string The audio content
     */
    public function speak(string $text, array $options = []): string
    {
        $params = array_merge([
            'model' => 'tts-1',
            'input' => $text,
            'voice' => 'alloy',
        ], $options);

        return $this->client->audio()->speech($params);
    }

    /**
     * Level 4: Check if agent is in autonomous mode
     */
    public function isAutonomous(): bool
    {
        return $this->mode === 'autonomous';
    }

    /**
     * Level 4: Get autonomy level (low, medium, high)
     */
    public function autonomyLevel(): ?string
    {
        return $this->autonomyLevel;
    }

    /**
     * Level 4: Get agent capabilities
     */
    public function getCapabilities(): ?array
    {
        return $this->capabilities;
    }

    /**
     * Level 4: Execute a task autonomously
     *
     * @param string $task
     * @param array $context
     * @return mixed
     * @throws Exception
     */
    public function execute(string $task, array $context = []): mixed
    {
        if (!$this->isAutonomous()) {
            throw new Exception('Agent is not in autonomous mode.');
        }

        // Decision logic based on autonomy level
        switch ($this->autonomyLevel) {
            case 'high':
                // Execute all actions without confirmation
                return $this->autonomousAction($task, $context);
            case 'medium':
                // Execute safe actions, ask for confirmation for critical
                if ($this->isSafeTask($task)) {
                    return $this->autonomousAction($task, $context);
                } else {
                    return 'Confirmation required for critical task: ' . $task;
                }
            case 'low':
            default:
                // Only suggest actions
                return 'Suggested action: ' . $task;
        }
    }

    /**
     * Level 4: Simulate autonomous action execution
     */
    protected function autonomousAction(string $task, array $context = []): string
    {
        // Example: self-monitoring and decision making
        $monitoring = $this->selfMonitor();
        $decision = $this->decide($task, $monitoring, $context);
        return "[AUTONOMOUS] Executed: {$decision}";
    }

    /**
     * Level 4: Simulate self-monitoring (health/status)
     */
    protected function selfMonitor(): array
    {
        // Example: return dummy system status
        return [
            'cpu' => rand(1, 100) . '%',
            'memory' => rand(1, 100) . '%',
            'status' => 'ok',
        ];
    }

    /**
     * Level 4: Simulate decision making
     */
    protected function decide(string $task, array $monitoring, array $context = []): string
    {
        // Example: simple logic based on monitoring
        if ($monitoring['status'] === 'ok') {
            return $task . ' [approved]';
        } else {
            return $task . ' [deferred: system not healthy]';
        }
    }

    /**
     * Level 4: Determine if a task is safe (dummy logic)
     */
    protected function isSafeTask(string $task): bool
    {
        // Example: only allow "monitor" and "report" as safe
        return str_contains(strtolower($task), 'monitor') || str_contains(strtolower($task), 'report');
    }

    /**
     * Asegura que los tool_calls tengan el formato correcto.
     *
     * @param array $toolCalls Los tool_calls a formatear
     * @return array Los tool_calls formateados
     * @throws RandomException
     */
    private function ensureToolCallFormat(array $toolCalls): array
    {
        return array_map(function($toolCall) {
            if (!isset($toolCall['type'])) {
                $toolCall['type'] = 'function';
            }
            if (!isset($toolCall['id'])) {
                $toolCall['id'] = 'call_' . bin2hex(random_bytes(8));
            }
            return $toolCall;
        }, $toolCalls);
    }

    /**
     * Extract and format tool calls from the given response or message.
     *
     * This method attempts to retrieve and format tool call data from the provided
     * OpenAI response object or the given message array. It supports multiple
     * access methods, including direct object access, array fallback, and reflection.
     * If the `finishReason` indicates tool calls should be present but they are
     * missing, an exception is thrown with details about a known client bug.
     *
     * @param mixed $response The OpenAI API response, typically an object containing choices
     * @param array $message The message array used as a fallback for tool call extraction
     * @param string $finishReason The finish reason indicating the processing status of the conversation
     * @return array The extracted and formatted tool calls
     *
     * @throws Exception If `finishReason` indicates 'tool_calls' and they are missing in the response
     */
    private function extractToolCalls(mixed $response, array $message, string $finishReason): array
    {
        $toolCalls = [];

        // ✅ Método 1: Acceso directo según documentación oficial
        if (is_object($response) && isset($response->choices[0]->message)) {
            $messageObj = $response->choices[0]->message;

            // Intentar diferentes propiedades conocidas
            if (property_exists($messageObj, 'toolCalls') && !empty($messageObj->toolCalls)) {
                $toolCalls = json_decode(json_encode($messageObj->toolCalls), true);
                //Log::info("[Agent Debug] Found tool_calls via object->toolCalls");
            } elseif (property_exists($messageObj, 'tool_calls') && !empty($messageObj->tool_calls)) {
                $toolCalls = json_decode(json_encode($messageObj->tool_calls), true);
                //Log::info("[Agent Debug] Found tool_calls via object->tool_calls");
            }
        }

        // ✅ Método 2: Acceso como array (fallback)
        if (empty($toolCalls)) {
            $toolCalls = $message['tool_calls'] ?? $message['toolCalls'] ?? [];
            if (!empty($toolCalls)) {
                //Log::info("[Agent Debug] Found tool_calls via array access");
            }
        }

        // ✅ Método 3: Reflection para propiedades privadas/protegidas
        if (empty($toolCalls) && is_object($response)) {
            $toolCalls = $this->extractToolCallsWithReflection($response->choices[0]->message);
            if (!empty($toolCalls)) {
                //Log::info("[Agent Debug] Found tool_calls via reflection");
            }
        }

        if (!empty($toolCalls)) {
            return $this->ensureToolCallFormat($toolCalls);
        }

        // Si finish_reason indica tool_calls pero no los encontramos, es un bug del cliente
        if ($finishReason === 'tool_calls') {
            Log::error("[Agent Debug] OpenAI client bug: tool_calls missing despite finish_reason");
            Log::error("[Agent Debug] Current client version: v0.8.5 - Update to v0.14.0+ recommended");

            // Instead of failing, throw specific exception
            throw new Exception(
                "OpenAI PHP client bug: tool_calls missing despite finish_reason='tool_calls'. " .
                "Current version: v0.8.5. Please update to openai-php/client v0.14.0+ which fixes this issue."
            );
        }

        return [];
    }

    /**
     * Extract tool calls or function calls from a message object using reflection.
     *
     * This method attempts to identify and extract tool-related data from the provided
     * object by examining its properties using PHP's reflection capabilities. It looks
     * for specific predefined property names that may hold the desired data.
     *
     * @param object $messageObj The object potentially containing tool call data
     * @return array An array of extracted tool call data, or an empty array if none is found
     *
     */
    private function extractToolCallsWithReflection(object $messageObj): array
    {
        $reflection = new \ReflectionObject($messageObj);
        $possibleProperties = ['toolCalls', 'tool_calls', 'function_calls', 'tools'];

        foreach ($possibleProperties as $property) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $value = $prop->getValue($messageObj);

                if (!empty($value)) {
                    //Log::info("[Agent Debug] Found tool_calls via reflection property: {$property}");
                    if (is_array($value)) {
                        return $value;
                    }
                    return json_decode(json_encode($value), true);
                }
            }
        }

        return [];
    }

    /**
     * Ejecuta un tool call usando las definiciones de herramientas registradas.
     *
     * @param string $functionName El nombre de la función a ejecutar
     * @param string $arguments Los argumentos JSON de la función
     * @param array $toolDefinitions Las definiciones de herramientas disponibles
     * @return string El resultado de la ejecución
     */
    private function executeToolCall(string $functionName, string $arguments, array $toolDefinitions): string
    {
        // Buscar la herramienta en las definiciones
        foreach ($toolDefinitions as $tool) {
            if (($tool['name'] ?? '') === $functionName) {
                // Si tenemos un callback, ejecutarlo
                if (isset($tool['callback']) && is_callable($tool['callback'])) {
                    try {
                        $args = json_decode($arguments, true) ?? [];

                        // Validate arguments if there's a schema
                        if (isset($tool['schema']) && is_array($tool['schema'])) {
                            $validatorClass = \Sapiensly\OpenaiAgents\Tools\ToolArgumentValidator::class;
                            $errors = $validatorClass::validate($args, $tool['schema']);
                            if (!empty($errors)) {
                                return "Argument validation error: " . implode('; ', $errors);
                            }
                        }
                        // Check cache first
                        $cachedResult = null;
                        if ($this->toolCacheManager && !$this->toolCacheManager->shouldBypassCache($functionName, $args)) {
                            $cachedResult = $this->toolCacheManager->getCachedResult($functionName, $args);
                        }

                        if ($cachedResult !== null) {
                            Log::info("[Agent Debug] Using cached result for tool: {$functionName}");
                            return $cachedResult;
                        }

                        // Execute tool and cache result
                        $result = (string) $tool['callback']($args);

                        if ($this->toolCacheManager && !$this->toolCacheManager->shouldBypassCache($functionName, $args)) {
                            $this->toolCacheManager->cacheResult($functionName, $args, $result);
                        }

                        return $result;
                    } catch (Exception $e) {
                        Log::error("[Agent Debug] Error executing tool: {$e->getMessage()}");
                        return "Error executing {$functionName}: {$e->getMessage()}";
                    }
                }
            }
        }

        return "Function {$functionName} executed (no implementation found)";
    }

    /**
     * Get limited messages preserving the system message and recent conversation.
     *
     * @param int|null $maxTurns Maximum number of turns to keep (user + assistant pairs)
     * @param int|null $maxInputTokens
     * @return array Limited message history
     */
    private function getLimitedMessages(int|null $maxTurns = null, int|null $maxInputTokens = null): array
    {
        $maxTurns ??= config('agents.default.max_turns', 10);
        $maxInputTokens = $maxInputTokens ?? config('agents.default.max_input_tokens', 4096);
        if (empty($this->messages)) {
            return [];
        }

        // Separar mensajes del sistema
        $systemMessages = [];
        $conversationMessages = [];

        foreach ($this->messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessages[] = $message;
            } else {
                $conversationMessages[] = $message;
            }
        }

        // Limitar por turnos primero (n * 2 mensajes)
        $maxConversationMessages = $maxTurns * 2;
        if (count($conversationMessages) > $maxConversationMessages) {
            $conversationMessages = array_slice($conversationMessages, -$maxConversationMessages);
        }

        // Ahora aplicar control de tokens estimados (tokens ≈ strlen/4)
        $trimmed = [];
        $totalTokens = 0;

        // Recorrer de forma inversa (del más nuevo al más antiguo)
        foreach (array_reverse($conversationMessages) as $msg) {
            $content = is_string($msg['content'])
                ? $msg['content']
                : json_encode($msg['content']);

            $tokens = (int)(strlen($content) / 4); // estimación simple

            if ($totalTokens + $tokens > $maxInputTokens) {
                break;
            }

            $trimmed[] = $msg;
            $totalTokens += $tokens;
        }

        // Restaurar orden cronológico
        $trimmed = array_reverse($trimmed);

        // Combinar mensajes del sistema con los de conversación limitados
        return [...$systemMessages, ...$trimmed];
    }


    /**
     * Execute registered function
     * @throws Exception
     */
    public function executeFunction(string $functionName, array $args = [])
    {
        if (!isset($this->functionImplementations[$functionName])) {
            throw new \Exception("Function {$functionName} not implemented");
        }

        return call_user_func($this->functionImplementations[$functionName], $args);
    }


    /**
     * Build content blocks based on the message structure.
     *
     * This method parses the input message to construct an array of content blocks.
     * It supports various content formats such as structured content, plain text,
     * images, and placeholders for future types like audio.
     *
     * @param array $msg The input message data containing content definitions.
     *
     * @return array An array of content blocks constructed from the input message.
     */
    private function buildContentBlocks(array $msg): array
    {
        $blocks = [];

        // Already structured? Keep it
        if (!empty($msg['content'][0]['type'])) {
            return $msg['content'];
        }

        if ($msg['role'] === 'assistant') {
            // Echoing assistant content: use output_text
            $blocks[] = ['type' => 'output_text', 'text' => $msg['content']];
        } else {
            // user or developer: input blocks
            $blocks[] = ['type' => 'input_text', 'text' => $msg['content']];

            if (!empty($msg['image_url'])) {
                $blocks[] = [
                    'type' => 'input_image',
                    'image_url' => ['url' => $msg['image_url']],
                ];
            }
            if (!empty($msg['audio_url'])) {
                $blocks[] = [
                    'type' => 'input_audio',
                    'audio_url' => ['url' => $msg['audio_url']],
                ];
            }
        }

        return $blocks;
    }

    /**
     * Normalizes a tool's structure to conform to OpenAI's expected format.
     *
     * This method transforms the given tool array into a defined structure compatible
     * with OpenAI's API expectations, ensuring consistency and validation. It checks
     * if the provided tool is already in a valid format or reconstructs it with the
     * required attributes.
     *
     * @param array $tool An associative array representing the tool, which may include
     *                    details such as name, description, schema, or parameters.
     *
     * @return array A normalized array formatted as per OpenAI's API specifications,
     *               containing the 'type' as 'function' and a validated 'function' structure.
     */
    private function normalizeToolForOpenAI(array $tool): array
    {
        // Si ya está en formato Responses API (type=function y name en tope)
        if (($tool['type'] ?? null) === 'function' && isset($tool['name'])) {
            // Asegurar parameters
            $parameters = $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()];
            if (isset($parameters['properties']) && is_array($parameters['properties']) && empty($parameters['properties'])) {
                $parameters['properties'] = new \stdClass();
            }
            $out = [
                'type' => 'function',
                'name' => $tool['name'],
                'parameters' => $parameters,
            ];
            if (isset($tool['description'])) {
                $out['description'] = $tool['description'];
            }
            return $out;
        }

        // Caso Chat-style: { type:function, function:{ name, parameters, description? } }
        if (($tool['type'] ?? null) === 'function' && isset($tool['function']['name'])) {
            $name = $tool['function']['name'];
            $parameters = $tool['function']['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()];
            if (isset($parameters['properties']) && is_array($parameters['properties']) && empty($parameters['properties'])) {
                $parameters['properties'] = new \stdClass();
            }
            $out = [
                'type' => 'function',
                'name' => $name,
                'parameters' => $parameters,
            ];
            if (isset($tool['function']['description'])) {
                $out['description'] = $tool['function']['description'];
            } elseif (isset($tool['description'])) {
                $out['description'] = $tool['description'];
            }
            return $out;
        }

        // Caso MCP schema genérico: { name, schema{...} | parameters{...}, description? }
        $name = $tool['name'] ?? 'unknown_function';
        // Detectar parámetros desde schema típico MCP
        $parameters = ['type' => 'object', 'properties' => new \stdClass()];
        if (isset($tool['schema']['schema'])) {
            $parameters = $tool['schema']['schema'];
        } elseif (isset($tool['schema']['type'])) {
            $parameters = $tool['schema'];
        } elseif (isset($tool['parameters'])) {
            $parameters = $tool['parameters'];
        } elseif (isset($tool['schema']) && is_array($tool['schema'])) {
            $parameters = $tool['schema'];
        }

        if (isset($parameters['properties']) && is_array($parameters['properties']) && empty($parameters['properties'])) {
            $parameters['properties'] = new \stdClass();
        }

        $out = [
            'type' => 'function',
            'name' => $name,
            'parameters' => $parameters,
        ];
        if (isset($tool['description'])) {
            $out['description'] = $tool['description'];
        } elseif (isset($tool['schema']['description'])) {
            $out['description'] = $tool['schema']['description'];
        }
        return $out;
    }


    /**
     * Handles the firing of a response event after processing an API response.
     *
     * @param string|null $message The input message for which a response was generated.
     * @param string $response The generated response from the system.
     * @param float $startTime The timestamp indicating when the response generation started.
     * @param float $endTime The timestamp indicating when the response generation ended.
     * @param mixed $apiResponse A response object or structure received from the API.
     * @param int $totalTokenUsage
     * @param string|null $api_method The specific method or endpoint alias used during the API interaction, if available.
     *
     * @return void
     */
    private function fireResponseEvent(string|null $message, string $response, float $startTime, float $endTime, $apiResponse, int $totalTokenUsage, string|null $api_method = null ): void
    {
        $metadata = [
            'model' => $this->options->get('model') ?? 'gpt-4o',
            'temperature' => $this->options->get('temperature'),
            'top_p' => $this->options->get('top_p'),
            'response_time' => round(($endTime - $startTime) * 1000, 2), // ms
            'message_count' => count($this->messages),
            'tools_used' => !empty($this->openAITools),
            'openai_tools' => $this->openAITools,
            'output_type' => $this->outputType ? 'structured' : 'text',
            'usage' => $apiResponse->usage ?? null,
            'estimated_total_token_usage' => $totalTokenUsage,
            'timestamp' => now()->toISOString(),
            'api_method' => $api_method,
            'success' => true,
        ];

        event(new AgentResponseGenerated(
            $this->getId(),
            $message,
            $response,
            $metadata
        ));
    }



    /**
     * Fire the error event.
     */
    private function fireErrorEvent(string|null $message, Exception $exception): void
    {
        $metadata = [
            'model' => $this->options->get('model') ?? 'gpt-4o',
            'temperature' => $this->options->get('temperature'),
            'top_p' => $this->options->get('top_p'),
            'message_count' => count($this->messages),
            'tools_used' => !empty($this->openAITools),
            'openai_tools' => $this->openAITools,
            'error' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_type' => get_class($exception),
            'timestamp' => now()->toISOString(),
            'api_method' => 'responses',
            'success' => false,
        ];

        event(new AgentResponseGenerated(
            $this->getId(),
            $message,
            '',
            $metadata
        ));
    }

}
