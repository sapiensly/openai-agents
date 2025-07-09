# OpenAI Agents Laravel Package - Guidelines for Junie

## Package Overview

This is a Laravel package for OpenAI Agents integration with a progressive enhancement architecture (4 levels). The package provides AI agent capabilities with tools, handoffs, MCP support, and autonomous features.

## Core Architecture

### Progressive Enhancement Levels

The package follows a progressive enhancement architecture with 4 levels:

1. **Level 1**: Simple conversational agents
   - Basic chat functionality
   - Simple tool integration

2. **Level 2**: Agents with tools and OpenAI official tools
   - Advanced tool system
   - RAG (Retrieval-Augmented Generation)
   - OpenAI tools (code interpreter, retrieval, web search)

3. **Level 3**: Multi-agent handoffs and workflows
   - Agent-to-agent handoffs
   - Context preservation
   - Security controls

4. **Level 4**: Autonomous agents with decision-making capabilities
   - Self-monitoring
   - Decision-making
   - Autonomous actions

### Key Components

- **Agent.php**: Main agent class with chat, streaming, and autonomous capabilities
- **Runner.php**: Manages agent execution, tools, guardrails, and handoffs
- **AgentServiceProvider.php**: Laravel service provider for dependency injection
- **AgentManager.php**: Manages multiple agent instances and configurations

## Coding Standards

### PHP Standards

- Use `declare(strict_types=1);` at the top of all PHP files
- Follow PSR-4 autoloading with namespace `Sapiensly\OpenaiAgents\`
- Use type hints for all parameters and return types
- Document all public methods with PHPDoc blocks
- Use nullable types (`?string`, `?array`) for optional parameters

### Class Structure

```php
<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Category;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Class Description
 * 
 * Detailed description of the class purpose and functionality.
 * Include usage examples and important notes.
 */
class ClassName
{
    /**
     * Property description
     *
     * @var Type
     */
    protected Type $property;

    /**
     * Constructor description
     *
     * @param Type $param Parameter description
     */
    public function __construct(Type $param)
    {
        $this->property = $param;
    }

    /**
     * Method description
     *
     * @param array $options Configuration options
     * @return self Returns instance for method chaining
     */
    public function method(array $options = []): self
    {
        // Implementation
        return $this;
    }
}
```

### Method Chaining

- Return `self` for fluent interfaces
- Use method chaining for configuration methods
- Example: `$agent->setOption('key', 'value')->enableFeature()`

### Error Handling

- Use custom exception classes in `Handoff/` and `Guardrails/`
- Log errors with context using `Log::error()`
- Provide meaningful error messages
- Use try-catch blocks for external API calls

## Design Patterns

### Service Provider Pattern

- Register services in `AgentServiceProvider::register()`
- Boot services in `AgentServiceProvider::boot()`
- Use singleton pattern for shared instances
- Publish configuration and assets

### Facade Pattern

- Provide `Agent` facade for simple access
- Register in `composer.json` extra.laravel.aliases
- Maintain backward compatibility

### Builder Pattern

- Use builder classes for complex configurations
- Example: `ToolDefinitionBuilder` for tool creation
- Fluent interface for configuration

### Observer Pattern

- Use events for lifecycle management
- Implement tracing and metrics collection
- Support custom event listeners

### Strategy Pattern

- Different handoff strategies in `Handoff/`
- Multiple transport protocols in `MCP/`
- Configurable guardrails and validators

## Key Subsystems

### Tool System

- Tools are registered with the Runner
- Tools can be simple callbacks or complex typed definitions
- Tool categories: Function Tools, OpenAI Tools, MCP Tools, RAG Tools, File Tools
- Tool validation with schema-based validation

### Handoff System

- Allows agents to hand off conversations to other agents
- Types: Basic, Advanced, Parallel, Reversible
- Components: HandoffOrchestrator, ContextAnalyzer, SecurityManager, ConversationStateManager

### MCP (Model Context Protocol)

- Allows communication with external models and tools
- Transport protocols: HTTP, STDIO, SSE
- Components: MCPManager, MCPClient, MCPServer, MCPTool

### Lifecycle Management

- Manages agent pooling, health checks, and resource management
- Ensures efficient use of resources
- Prevents memory leaks and resource exhaustion

## Configuration

- All configuration in `config/agents.php`
- Use environment variables with defaults
- Support multiple configuration profiles
- Validate configuration in service provider

## Testing

- Use Artisan commands for testing
- Test each progressive level
- Test all transport protocols
- Test error scenarios
- Test performance under load

## Documentation

- Maintain comprehensive documentation in `docs/` directory
- Document all public methods
- Include usage examples
- Explain complex algorithms
- Document configuration options

## Security Guidelines

- Use guardrails for input validation
- Sanitize user inputs
- Validate tool parameters
- Implement capability-based access control
- Use role-based permissions
- Validate handoff permissions
- Validate API keys and tokens
- Implement rate limiting
- Log security events

## Performance Considerations

- Use caching for tool results and responses
- Implement intelligent cache invalidation
- Use agent lifecycle management
- Use pooling for resource optimization
- Monitor memory usage in long-running processes
- Use PHP Fibers for async operations
- Implement queue-based processing
- Support streaming responses

## Development Workflow

1. Create feature branch
2. Add tests and commands
3. Update documentation
4. Follow coding standards
5. Test all levels

## Common Patterns

### Agent Creation

```php
// Simple agent
$agent = Agent::create(['model' => 'gpt-4o']);

// With tools
$agent = Agent::create([
    'model' => 'gpt-4o',
    'tools' => ['calculator', 'rag'],
]);

// Autonomous agent
$agent = Agent::create([
    'mode' => 'autonomous',
    'autonomy_level' => 'high',
    'capabilities' => ['monitor', 'decide', 'act'],
]);
```

### Runner Usage

```php
$runner = Agent::runner();
$runner->registerTool('tool_name', $callback);
$response = $runner->run('User message');
```

### Tool Definition

```php
$tool = new ToolDefinition([
    'name' => 'tool_name',
    'description' => 'Tool description',
    'parameters' => [
        'param_name' => [
            'type' => 'string',
            'description' => 'Parameter description',
            'required' => true,
        ],
    ],
    'callback' => function($args) {
        // Tool implementation
        return 'result';
    },
]);
```

## Integration Guidelines

- Use Laravel's service container
- Follow Laravel conventions
- Use Laravel's logging and caching
- Integrate with Laravel's queue system
- Use OpenAI PHP client
- Support multiple model providers
- Integrate with external APIs
- Use standard PHP libraries

## Maintenance and Updates

- Follow semantic versioning
- Maintain a changelog
- Document breaking changes
- Provide upgrade guides
- Test backward compatibility
- Support multiple Laravel versions
