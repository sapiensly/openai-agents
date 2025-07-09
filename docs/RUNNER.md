# Runner - Orchestration Engine for OpenAI Agents

The `Runner` class serves as an intelligent orchestration engine that controls and manages complex conversations with OpenAI-powered agents. Its primary purpose is to handle multi-turn interactions, tool invocations, and inter-agent communication.

## Core Functionality

The Runner executes controlled conversation loops that enable:

1. **Turn Management**: Controls how many iterations a conversation can perform (configurable maximum)
2. **Tool Integration**: Allows the agent to invoke external functions during the conversation
3. **Agent Handoffs**: Facilitates the transfer of control between different specialized agents
4. **Output Validation**: Ensures that responses conform to specific schemas

## Specific Capabilities

### 1. Tool Execution

```php
// Register a simple tool (with no specific parameters)
$runner->registerTool('echo', fn($args) => $args);
// During the conversation, the agent can use: [[tool:echo argument]]
```

Tools can also be registered with JSON schemas to leverage OpenAI function calling:

```php
// Tool with no parameters
$runner->registerFunctionTool('random_joke', function($args) {
    $jokes = [
        "Why don't scientists trust atoms? Because they make up everything!",
        "Why did the scarecrow win an award? Because he was outstanding in his field!"
    ];
    return $jokes[array_rand($jokes)];
}, [
    'name' => 'random_joke',
    'description' => 'Returns a random joke',
    'schema' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => []]
]);

// Tool with a single parameter
$runner->registerFunctionTool('reverse_text', function($args) {
    $text = $args['text'] ?? '';
    return strrev($text);
}, [
    'name' => 'reverse_text', 
    'description' => 'Reverses the given text',
    'schema' => [
        'type' => 'object',
        'properties' => [
            'text' => ['type' => 'string', 'description' => 'Text to reverse']
        ],
        'required' => ['text']
    ]
]);

// Tool with multiple parameters
$runner->registerFunctionTool('math_add', function($args) {
    $a = $args['a'] ?? 0;
    $b = $args['b'] ?? 0;
    return "The result of {$a} + {$b} is " . ($a + $b);
}, [
    'name' => 'math_add',
    'description' => 'Adds two numbers',
    'schema' => [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number'],
            'b' => ['type' => 'number']
        ],
        'required' => ['a', 'b']
    ]
]);
```

Tools can also be automatically generated from PHP types (though you'll need to adapt your function to receive an array of arguments):

### 2. Agent Handoffs

```php
$runner->registerAgent('spanish', $spanishAgent);
// The agent can transfer control using: [[handoff:spanish]]
```

This allows for specialized agents to handle different parts of a conversation, such as language translation, domain-specific knowledge, or different conversation modes.

### 3. Structured Output

```php
$schema = ['required' => ['done']];
$runner = new Runner($agent, maxTurns: 3, outputType: $schema);
// Ensures valid JSON format responses
```

The Runner can request and validate that responses match a specific JSON structure, making it easier to integrate with applications that need structured data.

### 4. Execution Modes

- **Synchronous**: `run()` - Traditional blocking execution
- **Asynchronous**: `runAsync()` - Using PHP Fibers for non-blocking execution
- **Streaming**: `runStreamed()` - Real-time results chunk by chunk

## Advanced Features

### Guardrails

Input and output validation mechanisms that can transform content or stop execution if certain conditions are met:

```php
$runner->addInputGuardrail(new CustomInputGuardrail());
$runner->addOutputGuardrail(new CustomOutputGuardrail());
```

### Tracing

Complete observability of each conversation turn, with the ability to log or send data to external systems:

```php
// In config/agents.php
'tracing' => [
    'enabled' => true,
    'processors' => [
        fn(array $record) => logger()->info('agent trace', $record),
    ],
],
```

### Safety Limits

Prevention of infinite loops through the `maxTurns` parameter, ensuring conversations eventually terminate.

## Workflow

1. **Initialization**: Created with an agent and configuration
2. **Main Loop**: Executes turns until final response or limit is reached
3. **Processing**: In each turn, analyzes the response looking for special commands
4. **Decision**: Continues, invokes tools, changes agent, or terminates
5. **Completion**: Returns the final response (string or structured array)

## Use Cases

The Runner is essential for complex use cases where you need the AI agent to:

- Interact with external systems and databases
- Maintain long, multi-turn conversations
- Collaborate with specialized agents
- Perform sequential operations
- Generate structured data for application integration
- Handle voice or multimodal interactions

By orchestrating these interactions, the Runner transforms simple chat functionality into powerful workflow automation capabilities.
