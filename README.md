# OpenAI Agents for Laravel

This package provides a lightweight integration of the [OpenAI PHP client](https://github.com/openai-php/client) with Laravel 12, inspired by the [OpenAI Agents Python SDK](https://github.com/openai/openai-agents-python).

## 🚀 Progressive Enhancement Architecture

This package implements a **4-level progressive enhancement architecture** that allows you to start simple and scale up as your needs grow:

### **Level 1: Conversational Agent**
**Concept:** Simple chat, Q&A, onboarding, FAQ bots.
```php
$response = Agent::simpleChat('Hello world');
```
**Config:**
```php
'progressive' => [
    'level' => 1,
    'auto_configure' => true,
],
```

### **Level 2: Agent with Tools**
**Concept:** Agent can use tools (functions, APIs, calculations, file ops, etc).
```php
$runner = Agent::runner();
$runner->registerTool('calculator', fn($args) => eval("return {$args['expression']};"));
$response = $runner->run('Calculate 15 * 23');
```

**OpenAI Official Tools:**
```php
$agent = Agent::create(['model' => 'gpt-4o']);
$agent->registerCodeInterpreter('cntr_your_container_id');
$agent->registerRetrieval(['k' => 3]);
$agent->registerWebSearch();
$response = $agent->chat('Analyze this data and search for recent information');
```

**Config:**
```php
'progressive' => [
    'level' => 2,
    'default_tools' => ['calculator', 'date', 'rag', 'vector_store', 'file_upload'],
],
'rag' => [
    'enabled' => true,
    'default_k' => 5,
    'default_r' => 0.7,
    'auto_setup' => true,
],
```

### **Level 3: Multi-Agents**
**Concept:** Multiple specialized agents collaborate (handoff, workflows).
```php
$runner = Agent::runner();
$runner->setHandoffOrchestrator(app(HandoffOrchestrator::class));
$response = $runner->run('I need technical help and pricing');
```
**Config:**
```php
'progressive' => [
    'level' => 3,
    'multi_agents' => true,
    'auto_handoff' => true,
],
```

### **Level 4: Autonomous Agents**
**Concept:** Agents can decide, act, monitor, and learn autonomously. Not just reactive: can initiate actions, monitor systems, and adapt. New features: `mode`, `autonomy_level`, `capabilities`, `execute()`, self-monitoring, decision making.
```php
$agent = Agent::create([
    'mode' => 'autonomous',
    'autonomy_level' => 'high',
    'capabilities' => ['monitor', 'decide', 'act', 'learn'],
    'tools' => ['system_diagnostics', 'auto_fix', 'alert_system'],
    'system_prompt' => 'You are an autonomous system monitor. Monitor and fix issues automatically.',
]);

$result = $agent->execute('Monitor system and fix issues automatically');
echo $result; // [AUTONOMOUS] Executed: Monitor system and fix issues automatically [approved]
```
**Config:**
```php
'progressive' => [
    'level' => 4,
    'autonomy_enabled' => true,
    'enterprise_features' => true,
],
```

---

## Features

### Core Capabilities
- **Progressive enhancement** - Start simple, scale smart
- **Multi-turn conversations** with context preservation
- **Tool integration** with automatic schema generation
- **Advanced handoff system** with context preservation and security controls
- **Model Context Protocol (MCP)** support with HTTP, STDIO, and SSE transports
- **Voice pipeline** for audio transcription and text-to-speech
- **Tracing and observability** for debugging and monitoring
- **Guardrails** for input/output validation and transformation
- **Structured output** with JSON schema support
- **Streaming support** for real-time responses
- **OpenAI Official Tools** - Code interpreter, retrieval, web search
- **Responses API** - Future-proof architecture with better performance

### MCP (Model Context Protocol) Features
- **Multiple transport protocols**: HTTP/JSON-RPC, STDIO, Server-Sent Events (SSE)
- **Resource discovery** and automatic tool registration
- **Local tool integration** via STDIO for CLI applications
- **Real-time streaming** with SSE for live data feeds
- **Comprehensive testing** with dedicated Artisan commands
- **Enterprise-ready** with detailed logging and statistics

### Advanced Capabilities
- **Parallel handoffs** for concurrent agent processing
- **Reversible handoffs** with state preservation
- **Intelligent caching** for performance optimization
- **Asynchronous processing** with PHP Fibers
- **Security controls** and permission management
- **Capability-based agent matching**

## Installation

### Via Composer

```bash
composer require sapiensly/openai-agents
```

### Publish Configuration

Publish the configuration file:

```bash
# This publishes config/agents.php to your Laravel app's config directory
php artisan vendor:publish --tag=config --provider="Sapiensly\\OpenaiAgents\\AgentServiceProvider"
```

### Environment Setup

Set your `OPENAI_API_KEY` in the environment file or edit `config/agents.php`:

```env
OPENAI_API_KEY=your-openai-api-key-here
OPENAI_MODEL=gpt-4o
OPENAI_TEMPERATURE=0.7
```

### Progressive Enhancement Configuration

Configure the progressive enhancement level in your `.env` file:

```env
# Start simple (Level 0)
AGENTS_PROGRESSIVE_LEVEL=0

# Add tools (Level 1)
AGENTS_PROGRESSIVE_LEVEL=1
AGENTS_AUTO_TOOLS=true

# Use declarative agents (Level 2)
AGENTS_PROGRESSIVE_LEVEL=2
AGENTS_DECLARATIVE_AGENTS=true

# Enterprise features (Level 3)
AGENTS_PROGRESSIVE_LEVEL=3
AGENTS_ENTERPRISE_FEATURES=true
```

## Quick Start

### Level 0: Simple Chat (Start Here!)

```php
use Sapiensly\OpenaiAgents\Agent;

// One-line usage - the simplest way to get started
$response = Agent::simpleChat('Hello world');
echo $response;
```

### Level 1: Add Tools

```php
// Create a runner with tools
$runner = Agent::runner();

// Register a simple tool
$runner->registerTool('calculator', function($args) {
    $expr = $args['expression'] ?? '0';
    return eval("return {$expr};");
});

// Use the runner
$response = $runner->run('Calculate 15 * 23');
```

### Level 2: Declarative Agents

```php
// Use pre-configured agents
$agent = Agent::use('customer_service');
$response = $agent->chat('I need help with my order');
```

### Command Line Usage

Send a message to the default agent:

```bash
php artisan agent:chat "Hello, how are you?"
```

Test all progressive enhancement levels:

```bash
php artisan agent:test-all-levels "What can you do?"
```

## Testing

This package includes comprehensive Artisan commands for testing functionality within your Laravel application:

```bash
# Test all progressive enhancement levels
php artisan agent:test-all-levels "What can you do?"

# Test individual levels
php artisan agent:test-level1 "Hello"
php artisan agent:test-level2 "What's the date?"
php artisan agent:test-level3 "I need help with pricing"
php artisan agent:test-level4 "Monitor system status"

# Test streaming functionality
php artisan agent:test-streaming

# Test RAG functionality
php artisan agent:test-rag "What is Laravel?"

# Test voice pipeline
php artisan agent:test-voice-pipeline

# Test MCP functionality
php artisan agent:test-mcp-http
```

## Documentation

- [Quick Start Guide](docs/QUICK_START.md)
- [RAG Guide](docs/RAG_GUIDE.md)
- [Tools Documentation](docs/TOOLS.md)
- [MCP Documentation](docs/MCP.md)
- [Handoff Documentation](docs/HANDOFF.md)
- [Tracing Documentation](docs/TRACING.md)
- [Voice Documentation](docs/VOICE.md)
- [Streaming Documentation](docs/STREAMING.md)
- [Lifecycle Management](docs/LIFECYCLE_MANAGEMENT.md)
- [Progressive Enhancement](docs/PROGRESSIVE_ENHANCEMENT.md)
- [Model Providers](docs/MODEL_PROVIDERS.md)
- [Visualization](docs/VISUALIZATION.md)

## Contributing

Please see [CONTRIBUTING.md](docs/CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.
