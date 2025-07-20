<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents;

use Exception;
use Illuminate\Support\Facades\Log;
use OpenAI\Client;
use Random\RandomException;
use Sapiensly\OpenaiAgents\Events\AgentResponseGenerated;
use Sapiensly\OpenaiAgents\Tools\ToolCacheManager;
use Sapiensly\OpenaiAgents\Tools\VectorStoreTool;

class Agent
{
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
     * Total tokens used by the agent.
     */
    protected int $totalTokens = 0;

    /**
     * Create a new Agent instance.
     *
     * @param Client $client The OpenAI client instance
     * @param AgentOptions|array|null $options Configuration options for the agent
     * @param string|null $systemPrompt Optional system prompt to initialize the conversation
     */
    public function __construct(Client $client, AgentOptions|array|null $options = null, string|null $systemPrompt = null, string|null $id = null, ToolCacheManager|null $toolCacheManager = null)
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

        // Handle system prompt from either parameter or options
        $finalSystemPrompt = $systemPrompt;
        if ($finalSystemPrompt === null && $agentOptions->get('system_prompt') !== null) {
            $finalSystemPrompt = $agentOptions->get('system_prompt');
        }

        if (!empty($finalSystemPrompt)) {
            $this->messages[] = ['role' => 'system', 'content' => $finalSystemPrompt];
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
     * @param AgentOptions|array $options Optional configuration options
     * @return Runner A configured runner instance
     */
    public static function runner(AgentOptions|array $options = []): Runner
    {
        $manager = app(AgentManager::class);
        $agent = $manager->agent($options);
        return new Runner($agent);
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
     * @throws \InvalidArgumentException if required config is missing
     */
    private function registerOpenAITools(string $type, array $config = []): self
    {
        $tool = match($type) {
            'code_interpreter' => [
                'type' => 'code_interpreter',
                'container' => isset($config['container']) && str_starts_with($config['container'], 'cntr')
                    ? $config['container']
                    : throw new \InvalidArgumentException("You must provide a valid 'container' ID (starting with 'cntr') for code_interpreter. Example: ['container' => 'cntr_xxx...']"),
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
            default => throw new \InvalidArgumentException("Unknown OpenAI tool type: {$type}")
        };
        $this->openAITools[] = $tool;
        return $this;
    }

    public function registerCodeInterpreter(string $containerId): self
    {
        return $this->registerOpenAITools('code_interpreter', ['container' => $containerId]);
    }

    public function registerWebSearch(): self
    {
        return $this->registerOpenAITools('web_search');
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
     * @throws Exception
     */
    private function chatWithResponsesAPI(string $message, array $toolDefinitions, mixed $outputType): string
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
        $current = ['content' => $message];
        // TODO: Add support for images and audio URLs
        if (!empty($imageUrl)) $current['image_url'] = $imageUrl;
        if (!empty($audioUrl)) $current['audio_url'] = $audioUrl;

        $inputItems[] = [
            'role' => 'user',
            'content' =>  $this->buildContentBlocks(['role' => 'user'] + $current),
        ];

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

        // Prepare the parameters for the Responses API
        $params = [
            'model' => $this->options->get('model') ?? 'gpt-4o',
            'instructions' => $this->options->get('instructions') ?? '', // instructions replaces system prompt in Responses API
            'input' => $inputItems,
            'temperature' => $this->options->get('temperature') ?? null,
            'top_p' => $this->options->get('top_p') ?? null,
            'tools' => $this->options->get('tools') ?? []
            //'modalities' => ['text', 'audio'], // request both formats (optional)
        ];

        // Add OpenAI official tools
        if (!empty($this->openAITools)) {
            $params['tools'] = $this->openAITools;
        }

        // Add function tools if any
        if (!empty($toolDefinitions)) {
            $functionTools = [];
            foreach ($toolDefinitions as $tool) {
                if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                    $functionTools[] = $tool;
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
                    $functionTool = [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool['name'] ?? 'unknown_function',
                            'parameters' => $parameters,
                        ]
                    ];
                    if (isset($tool['description'])) {
                        $functionTool['function']['description'] = $tool['description'];
                    } elseif (isset($tool['schema']['description'])) {
                        $functionTool['function']['description'] = $tool['schema']['description'];
                    }
                    $functionTools[] = $functionTool;
                }
            }

            if (!empty($functionTools)) {
                $params['tools'] = array_merge($params['tools'] ?? [], $functionTools);
            }
        }

        // Handle output type for structured responses
        $outputType = $outputType ?? $this->outputType;
        if ($outputType !== null) {
            $params['response_format'] = ['type' => 'json_object'];
        }


        try {
            $response = $this->client->responses()->create($params);
            $endTime = microtime(true);

            // Extract content from Responses API response
            $response_content = '';
            $role = $response->output[0]->role ?? 'assistant';

            $outputTokenCount = 0;
            if ($response->output) {
                foreach ($response->output as $output) {
                    // Check if the output has content
                    if (!isset($output->content) || !is_array($output->content)) {
                        continue; // Skip if no content
                    }
                    foreach ($output->content as $content) {
                        if (isset($content->type) && $content->type === 'output_text') {
                            $response_content .=  $content->text ?? '';
                            $outputTokenCount += (int) (strlen($content->text) / 4); // Estimate of token count
                        }
                    }
                }
            }

            $this->addTokenUsage($inputTokenCount + $outputTokenCount);

            if (!empty($response_content)) {
                $this->messages[] = ['role' => $role, 'content' => $response_content];
            }

            // EMIT THE EVENT WITH MESSAGE AND SOME ANALYTICS DATA
            $this->fireResponseEvent($message, $response_content, $startTime, $endTime, $response, $this->getTokenUsage() , 'Responses API');

            return $response_content;

        } catch (Exception $e) {
            Log::error("[Agent Debug] Chat With Responses API error: {$e->getMessage()}");

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
     * Handles the firing of a response event after processing an API response.
     *
     * @param string $message The input message for which a response was generated.
     * @param string $response The generated response from the system.
     * @param float $startTime The timestamp indicating when the response generation started.
     * @param float $endTime The timestamp indicating when the response generation ended.
     * @param mixed $apiResponse A response object or structure received from the API.
     * @param int $totalUsedTokens The estimated total number of tokens used in the conversation.
     * @param string|null $api_method The specific method or endpoint alias used during the API interaction, if available.
     *
     * @return void
     */
    private function fireResponseEvent(string $message, string $response, float $startTime, float $endTime, $apiResponse, int $totalTokenUsage, string|null $api_method = null ): void
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
    private function fireErrorEvent(string $message, Exception $exception): void
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
