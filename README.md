# OpenAI Agents for Laravel

A powerful, flexible Laravel package that integrates OpenAI's AI capabilities into your applications through a progressive enhancement architecture. This package bridges the [OpenAI PHP client](https://github.com/openai-php/client) with Laravel Framework, inspired by the [OpenAI Agents Python SDK](https://github.com/openai/openai-agents-python).

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sapiensly/openai-agents.svg)](https://packagist.org/packages/sapiensly/openai-agents)
[![Total Downloads](https://img.shields.io/packagist/dt/sapiensly/openai-agents.svg)](https://packagist.org/packages/sapiensly/openai-agents)
[![License](https://img.shields.io/github/license/sapiensly/openai-agents.svg)](LICENSE)

## Table of Contents
- [Overview](#overview)
- [Progressive Enhancement Architecture](#-progressive-enhancement-architecture)
- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Components](#core-components)
- [Security Guidelines](#security-guidelines)
- [Performance Considerations](#performance-considerations)
- [Testing](#testing)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)
- [Changelog](#changelog)

## Overview

OpenAI Agents for Laravel is designed with a unique **progressive enhancement architecture** that allows you to start with simple AI integrations and gradually scale up to enterprise-level autonomous agents. This approach ensures you only implement the complexity you need, when you need it.

**Key Differentiators:**
- **Progressive Enhancement Architecture** - Start simple, scale smart with our 4-level approach
- **Comprehensive Tool System** - From simple functions to complex OpenAI official tools
- **Advanced Handoff System** - Specialized agents collaborate seamlessly
- **Flexible MCP support** - Allows agent interactions with HTTP, STDIO, and SSE support
- **Autonomous Capabilities** - Self-monitoring, decision-making agents
- **Enterprise-Ready** - Security controls, performance optimization, and comprehensive logging
- **Detailed Documentation** - Guides for every feature, from basic to advanced
- **Artisan Commands** - Comprehensive testing and debugging tools


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

### Prerequisites
- PHP 8.1 or higher
- Laravel 10.0 or higher
- OpenAI API key
- Composer

### Step 1: Install via Composer
```bash
composer require sapiensly/openai-agents
```

### Step 2: Publish Configuration
```bash
php artisan vendor:publish --tag=config --provider="Sapiensly\\OpenaiAgents\\AgentServiceProvider"
```

### Step 3: Set Up Environment Variables
Add these variables to your `.env` file:
```env
OPENAI_API_KEY=your-openai-api-key-here
OPENAI_MODEL=gpt-4o
OPENAI_TEMPERATURE=0.7
AGENTS_PROGRESSIVE_LEVEL=1
```

### Step 4: Configure Progressive Enhancement Level
Choose the appropriate level for your needs:

```env
# Level 1: Simple conversational agents
AGENTS_PROGRESSIVE_LEVEL=1
AGENTS_AUTO_CONFIGURE=true

# Level 2: Agents with tools and OpenAI official tools
AGENTS_PROGRESSIVE_LEVEL=2
AGENTS_DEFAULT_TOOLS=true

# Level 3: Multi-agent handoffs and workflows
AGENTS_PROGRESSIVE_LEVEL=3
AGENTS_MULTI_AGENTS=true

# Level 4: Autonomous agents with decision-making capabilities
AGENTS_PROGRESSIVE_LEVEL=4
AGENTS_AUTONOMY_ENABLED=true
```

### Step 5: Run Database Migrations (Optional)
If you plan to use conversation history storage:
```bash
php artisan migrate
```

## Laravel Integration

### Service Container
```php
// Bind custom implementations
$this->app->bind(AgentInterface::class, CustomAgent::class);

// Resolve from container
$agent = app(AgentInterface::class);
```

### Facades
```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$response = Agent::simpleChat('Hello');
```

### Middleware
```php
// In RouteServiceProvider.php
Route::middleware('web')
    ->group(function () {
        Route::get('/chat', function () {
            return view('chat');
        })->middleware('agents.auth');
    });
```

### Events
```php
// Listen for agent events
Event::listen(AgentResponseGenerated::class, function ($event) {
    Log::info('Agent response: ' . $event->response);
});
```

### Queue Integration
```php
// Queue a chat job
AgentChatJob::dispatch('Hello world')
    ->onQueue('agents');
```

## Quick Start

### Common Use Cases

#### Simple Conversational Bot
```php
use Sapiensly\OpenaiAgents\Agent;

// One-line usage - the simplest way to get started
$response = Agent::simpleChat('Hello world');
echo $response;
```

#### Knowledge Base Assistant
```php
$agent = Agent::create(['model' => 'gpt-4o']);
$agent->registerRetrieval(['k' => 3]);
$response = $agent->chat('What is our refund policy?');
```

#### Data Analysis Assistant
```php
$agent = Agent::create(['model' => 'gpt-4o']);
$agent->registerCodeInterpreter('cntr_your_container_id');
$response = $agent->chat('Analyze this CSV data and create a chart');
```

#### System Monitoring Agent
```php
$agent = Agent::create([
    'mode' => 'autonomous',
    'autonomy_level' => 'high',
    'capabilities' => ['monitor', 'decide', 'act'],
    'tools' => ['system_diagnostics', 'auto_fix', 'alert_system'],
]);
$result = $agent->execute('Monitor system and fix issues automatically');
```

### Progressive Enhancement Examples

#### Level 1: Simple Chat
```php
$response = Agent::simpleChat('Hello world');
```

#### Level 2: Add Tools
```php
$runner = Agent::runner();
$runner->registerTool('calculator', function($args) {
    $expr = $args['expression'] ?? '0';
    return eval("return {$expr};");
});
$response = $runner->run('Calculate 15 * 23');
```

#### Level 3: Multi-Agents
```php
$runner = Agent::runner();
$runner->setHandoffOrchestrator(app(HandoffOrchestrator::class));
$response = $runner->run('I need technical help and pricing');
```

#### Level 4: Autonomous Agents
```php
$agent = Agent::create([
    'mode' => 'autonomous',
    'autonomy_level' => 'high',
    'capabilities' => ['monitor', 'decide', 'act'],
]);
$result = $agent->execute('Monitor system and fix issues automatically');
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

## Core Components

### Agent
The main class that provides chat, streaming, and autonomous capabilities:
```php
$agent = Agent::create(['model' => 'gpt-4o']);
$response = $agent->chat('Hello world');
```

### Runner
Manages agent execution, tools, guardrails, and handoffs:
```php
$runner = Agent::runner();
$runner->registerTool('calculator', fn($args) => eval("return {$args['expression']};"));
$response = $runner->run('Calculate 15 * 23');
```

### AgentManager
Manages multiple agent instances and configurations:
```php
$manager = app(AgentManager::class);
$agent = $manager->agent('customer_service');
```

## Security Guidelines

This package implements several security features to ensure safe AI agent operations:

- **Input Validation**: All user inputs are validated before processing
- **Tool Parameter Validation**: Parameters passed to tools are validated against schemas
- **Capability-based Access Control**: Agents only have access to tools they're explicitly granted
- **Role-based Permissions**: Control which users can access which agents and capabilities
- **Handoff Security**: Validate permissions before allowing agent handoffs
- **Rate Limiting**: Prevent abuse through configurable rate limits
- **Comprehensive Logging**: Security events are logged for audit purposes

Configure security settings in `config/agents.php`:
```php
'security' => [
    'validate_inputs' => true,
    'validate_outputs' => true,
    'rate_limit' => 100, // requests per minute
    'log_security_events' => true,
],
```

## Performance Considerations

Optimize your agent implementations with these performance features:

- **Intelligent Caching**: Cache tool results and responses for faster execution
- **Agent Lifecycle Management**: Control agent instantiation and destruction
- **Resource Pooling**: Optimize resource usage with agent pooling
- **Memory Management**: Monitor and control memory usage in long-running processes
- **Asynchronous Processing**: Use PHP Fibers for non-blocking operations
- **Queue-based Processing**: Offload intensive tasks to Laravel queues
- **Streaming Responses**: Reduce time-to-first-byte with streaming

Configure performance settings:
```php
'performance' => [
    'enable_caching' => true,
    'cache_ttl' => 3600,
    'use_pooling' => true,
    'pool_size' => 10,
    'use_queues' => true,
    'queue_name' => 'agents',
],
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

### Getting Started
- [Quick Start Guide](docs/QUICK_START.md) - Begin using the package in minutes
- [Progressive Enhancement](docs/PROGRESSIVE_ENHANCEMENT.md) - Understanding the 4-level architecture
- [Configuration Reference](docs/CONFIGURATION.md) - Complete configuration options

### Core Features
- [Tools Documentation](docs/TOOLS.md) - Creating and using tools
- [RAG Guide](docs/RAG_GUIDE.md) - Retrieval-Augmented Generation
- [Handoff Documentation](docs/HANDOFF.md) - Agent-to-agent handoffs
- [MCP Documentation](docs/MCP.md) - Model Context Protocol

### Advanced Features
- [Voice Documentation](docs/VOICE.md) - Audio transcription and text-to-speech
- [Streaming Documentation](docs/STREAMING.md) - Real-time responses
- [Tracing Documentation](docs/TRACING.md) - Debugging and monitoring
- [Lifecycle Management](docs/LIFECYCLE_MANAGEMENT.md) - Resource optimization

### Integration
- [Model Providers](docs/MODEL_PROVIDERS.md) - Using alternative AI providers
- [Visualization](docs/VISUALIZATION.md) - Visualizing agent interactions
- [Security Best Practices](docs/SECURITY.md) - Securing your agent implementations
- [Performance Optimization](docs/PERFORMANCE.md) - Optimizing for scale

## Contributing

Please see [CONTRIBUTING.md](docs/CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.
