# Agent Tinker - Interactive Agent Development Environment

## Overview

The `agents:tinker` command provides an interactive REPL (Read-Eval-Print Loop) environment specifically designed for developing and testing OpenAI agents. It pre-loads all necessary variables and helpers, making agent development as seamless as possible.

## Quick Start

```bash
# Start interactive Tinker session
php artisan agents:tinker

# Execute specific code and exit
php artisan agents:tinker --execute="$result = \$runner->run('Hello'); echo \$result;"
```

## Pre-loaded Variables

When you start `agents:tinker`, the following variables are automatically available:

| Variable | Type | Description |
|----------|------|-------------|
| `$agent` | `Agent` | Default agent instance |
| `$runner` | `Runner` | Runner instance for executing agents |
| `$agentManager` | `AgentManager` | Agent manager for creating/managing agents |
| `$helpers` | `AgentHelpers` | Static helper class with utility methods |

## Basic Usage Examples

### Simple Agent Interaction

```php
// Basic conversation
$result = $runner->run('Hello, how are you?');
echo $result;

// Creative responses
$result = $runner->run('Write a haiku about programming');
echo $result;
```

### Streaming Responses

```php
// Stream response in real-time
foreach ($runner->runStreamed('Tell me a story about a robot') as $chunk) {
    echo $chunk;
}

// Stream with processing
$fullResponse = '';
foreach ($runner->runStreamed('Explain quantum computing') as $chunk) {
    $fullResponse .= $chunk;
    echo $chunk;
}
echo "\nTotal length: " . strlen($fullResponse);
```

### Agent Management

```php
// Create a new agent with custom instructions
$customAgent = $agentManager->agent([], 'You are a helpful coding assistant');

// Create agent with specific model
$gpt4Agent = $agentManager->agent(['model' => 'gpt-4'], 'You are a creative writer');

// Get runner for custom agent
$customRunner = $agentManager->runner($customAgent);
$result = $customRunner->run('Write a function in PHP');
```

## Advanced Usage with Helpers

### Using AgentHelpers

```php
// Get help and examples
$helpers::help();

// Quick test
$helpers::quickTest();

// Test streaming
$helpers::testStreaming();

// Create agent with helpers
$agent = $helpers::createAgent('Coder', 'You are a programming expert');
$result = $helpers::runAgent($agent, 'Write a Python function');
```

### Tool Management

```php
// Get all available tools
$tools = $helpers::getTools();
print_r($tools);

// Get specific tool
$tool = $helpers::getTool('tool_name');
```

### Advanced Features

```php
// Get handoff orchestrator
$handoff = $helpers::getHandoffOrchestrator();

// Get tracing instance
$tracing = $helpers::getTracing();

// Access agent properties
echo "Agent ID: " . $agent->getId();
echo "Model: " . $agent->getOptions()['model'] ?? 'default';
```

## Command Line Usage

### Interactive Mode

```bash
# Start interactive session
php artisan agents:tinker

# In the session:
> $result = $runner->run('Hello');
> echo $result;
> exit
```

### Execute Mode

```bash
# Execute single command
php artisan agents:tinker --execute="echo \$runner->run('Hello');"

# Execute multiple commands
php artisan agents:tinker --execute="
\$result = \$runner->run('Write a short poem');
echo \$result;
echo 'Length: ' . strlen(\$result);
"

# Test streaming
php artisan agents:tinker --execute="
foreach (\$runner->runStreamed('Story about space') as \$chunk) {
    echo \$chunk;
}
"
```

## Development Workflows

### Testing Agent Responses

```php
// Test different prompts
$prompts = [
    'Hello',
    'What is AI?',
    'Write a function',
    'Tell me a joke'
];

foreach ($prompts as $prompt) {
    echo "Testing: $prompt\n";
    $result = $runner->run($prompt);
    echo "Response: $result\n\n";
}
```

### Comparing Models

```php
// Test different models
$models = ['gpt-3.5-turbo', 'gpt-4'];

foreach ($models as $model) {
    echo "Testing model: $model\n";
    $testAgent = $agentManager->agent(['model' => $model]);
    $testRunner = $agentManager->runner($testAgent);
    $result = $testRunner->run('Explain quantum physics in simple terms');
    echo "Response: $result\n\n";
}
```

### Debugging Agent Behavior

```php
// Check agent configuration
echo "Agent ID: " . $agent->getId() . "\n";
echo "Model: " . ($agent->getOptions()['model'] ?? 'default') . "\n";
echo "System Prompt: " . $agent->getSystemPrompt() . "\n";

// Test with different system prompts
$agent->setSystemPrompt('You are a helpful assistant that gives concise answers');
$result = $runner->run('What is the capital of France?');
echo $result;
```

### Performance Testing

```php
// Measure response time
$start = microtime(true);
$result = $runner->run('Hello');
$end = microtime(true);
echo "Response time: " . ($end - $start) * 1000 . "ms\n";

// Test streaming performance
$start = microtime(true);
$chunks = 0;
foreach ($runner->runStreamed('Write a long story') as $chunk) {
    $chunks++;
    echo $chunk;
}
$end = microtime(true);
echo "\nStreaming time: " . ($end - $start) * 1000 . "ms\n";
echo "Total chunks: $chunks\n";
```

## Error Handling

### Common Issues and Solutions

```php
// Handle API errors
try {
    $result = $runner->run('Hello');
    echo $result;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check if agent is properly configured
if ($agent === null) {
    echo "Agent not initialized\n";
} else {
    echo "Agent ready: " . get_class($agent) . "\n";
}

// Validate runner
if ($runner === null) {
    echo "Runner not initialized\n";
} else {
    echo "Runner ready: " . get_class($runner) . "\n";
}
```

## Integration Examples

### With Laravel Models

```php
// Use with Eloquent models
$user = \App\Models\User::find(1);
$context = "User: {$user->name}, Email: {$user->email}";
$result = $runner->run("Generate a personalized greeting for: $context");
```

### With Configuration

```php
// Check current configuration
echo "OpenAI API Key: " . (config('openai.api_key') ? 'Set' : 'Not set') . "\n";
echo "Default Model: " . config('agents.defaults.model', 'gpt-3.5-turbo') . "\n";
echo "Max Turns: " . config('agents.defaults.max_turns', 5) . "\n";
```

### With Caching

```php
// Check cache status
$cacheStats = $runner->getToolCacheStats();
print_r($cacheStats);

$responseCacheStats = $runner->getResponseCacheStats();
print_r($responseCacheStats);
```

## Best Practices

### 1. Use Descriptive Variable Names

```php
// Good
$codingAgent = $agentManager->agent([], 'You are a programming expert');
$codingResult = $codingAgent->run('Write a PHP function');

// Avoid
$agent1 = $agentManager->agent();
$result = $agent1->run('Write code');
```

### 2. Handle Long Responses

```php
// For long responses, use streaming
foreach ($runner->runStreamed('Write a comprehensive guide') as $chunk) {
    echo $chunk;
    // Process chunk by chunk
}
```

### 3. Test Different Scenarios

```php
// Test various input types
$testInputs = [
    'Simple question',
    'Complex multi-step request',
    'Creative writing prompt',
    'Technical problem'
];

foreach ($testInputs as $input) {
    echo "Testing: $input\n";
    $result = $runner->run($input);
    echo "Response length: " . strlen($result) . "\n\n";
}
```

### 4. Monitor Performance

```php
// Track response times
$times = [];
for ($i = 0; $i < 5; $i++) {
    $start = microtime(true);
    $runner->run('Hello');
    $times[] = microtime(true) - $start;
}

echo "Average response time: " . array_sum($times) / count($times) * 1000 . "ms\n";
```

## Troubleshooting

### Common Error Messages

| Error | Cause | Solution |
|-------|-------|----------|
| `Agent not initialized` | Missing OpenAI configuration | Check `config/openai.php` |
| `Runner not initialized` | Agent creation failed | Verify API key and model |
| `TypeError: Argument #1` | Wrong parameter order | Use `$runner->run('message')` |
| `API Error` | OpenAI API issues | Check API key and quota |

### Debug Commands

```php
// Check environment
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Laravel Version: " . app()->version() . "\n";
echo "OpenAI Config: " . (config('openai.api_key') ? 'OK' : 'Missing') . "\n";

// Check agent state
echo "Agent Class: " . get_class($agent) . "\n";
echo "Runner Class: " . get_class($runner) . "\n";
echo "Agent ID: " . $agent->getId() . "\n";
```

## Configuration

### Environment Variables

Make sure these are set in your `.env` file:

```env
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_ORGANIZATION=your_organization_id_here
```

### Package Configuration

The package uses configuration from `config/agents.php`:

```php
// Check current configuration
echo "Default Model: " . config('agents.defaults.model') . "\n";
echo "Max Turns: " . config('agents.defaults.max_turns') . "\n";
echo "Cache Enabled: " . (config('agents.tools.cache.enabled') ? 'Yes' : 'No') . "\n";
```

## Advanced Features

### Custom System Prompts

```php
// Create agent with custom system prompt
$customAgent = $agentManager->agent([], 'You are a helpful coding assistant that writes clean, documented code');
$customRunner = $agentManager->runner($customAgent);
$result = $customRunner->run('Write a PHP function to validate email');
```

### Tool Integration

```php
// Register custom tools
$runner->registerStringTool('greet', function($args) {
    $name = $args['name'] ?? 'User';
    return "Hello, $name!";
}, 'name', 'Name to greet');

// Test tool
$result = $runner->run('Use the greet tool with name "Alice"');
```

### Handoff Testing

```php
// Test handoff functionality
$handoff = $helpers::getHandoffOrchestrator();
$result = $handoff->processRequest('Transfer to coding agent');
```

## Conclusion

The `agents:tinker` command provides a powerful interactive environment for developing and testing OpenAI agents. With pre-loaded variables, comprehensive helpers, and seamless integration with Laravel, it offers a development experience comparable to the Python OpenAI Agents SDK.

For more information about the package features, see the main documentation and other markdown files in this package. 