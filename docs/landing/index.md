# OpenAI Agents for Laravel

A powerful, flexible Laravel package that integrates OpenAI's AI capabilities into your applications through a progressive enhancement architecture. This package bridges the [OpenAI PHP client](https://github.com/openai-php/client) with Laravel Framework, inspired by the [OpenAI Agents Python SDK](https://github.com/openai/openai-agents-python).

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sapiensly/openai-agents.svg)](https://packagist.org/packages/sapiensly/openai-agents)
[![Total Downloads](https://img.shields.io/packagist/dt/sapiensly/openai-agents.svg)](https://packagist.org/packages/sapiensly/openai-agents)
[![License](https://img.shields.io/github/license/sapiensly/openai-agents.svg)](LICENSE)

## 🌟 Overview

OpenAI Agents for Laravel is designed with a unique **progressive enhancement architecture** that allows you to start with simple AI integrations and gradually scale up to enterprise-level autonomous agents. This approach ensures you only implement the complexity you need, when you need it.

**Key Differentiators:**
- **Progressive Enhancement Architecture** - Start simple, scale smart with our 4-level approach
- **Comprehensive Tool System** - From simple functions to complex OpenAI official tools
- **Advanced Handoff System** - Specialized agents collaborate seamlessly
- **Flexible MCP support** - Allows agent interactions with HTTP, STDIO, and SSE support
- **Autonomous Capabilities** - Self-monitoring, decision-making agents
- **Enterprise-Ready** - Security controls, performance optimization, and comprehensive logging

## 🚀 Progressive Enhancement Architecture

This package implements a **4-level progressive enhancement architecture** that allows you to start simple and scale up as your needs grow:

<div class="level-cards">
  <div class="level-card">
    <h3>Level 1: Conversational Agent</h3>
    <p>Simple chat, Q&A, onboarding, FAQ bots</p>
    <pre><code>$response = Agent::simpleChat('Hello world');</code></pre>
  </div>
  <div class="level-card">
    <h3>Level 2: Agent with Tools</h3>
    <p>Agent can use tools (functions, APIs, calculations, etc)</p>
    <pre><code>$runner = Agent::runner();
$runner->registerTool('calculator', 
  fn($args) => eval("return {$args['expression']};"));
$response = $runner->run('Calculate 15 * 23');</code></pre>
  </div>
  <div class="level-card">
    <h3>Level 3: Multi-Agents</h3>
    <p>Multiple specialized agents collaborate (handoff, workflows)</p>
    <pre><code>$runner = Agent::runner();
$runner->setHandoffOrchestrator(
  app(HandoffOrchestrator::class));
$response = $runner->run('I need technical help and pricing');</code></pre>
  </div>
  <div class="level-card">
    <h3>Level 4: Autonomous Agents</h3>
    <p>Agents can decide, act, monitor, and learn autonomously</p>
    <pre><code>$agent = Agent::create([
  'mode' => 'autonomous',
  'autonomy_level' => 'high',
  'capabilities' => ['monitor', 'decide', 'act'],
]);
$result = $agent->execute('Monitor system and fix issues');</code></pre>
  </div>
</div>

## ✨ Features

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

### MCP (Model Context Protocol) Features
- **Multiple transport protocols**: HTTP/JSON-RPC, STDIO, Server-Sent Events (SSE)
- **Resource discovery** and automatic tool registration
- **Local tool integration** via STDIO for CLI applications
- **Real-time streaming** with SSE for live data feeds

### Advanced Capabilities
- **Parallel handoffs** for concurrent agent processing
- **Reversible handoffs** with state preservation
- **Intelligent caching** for performance optimization
- **Asynchronous processing** with PHP Fibers
- **Security controls** and permission management
- **Capability-based agent matching**

## 📦 Installation

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

## 🚀 Quick Start

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

## 🧩 Core Components

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

## 🛡️ Security Guidelines

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

## ⚡ Performance Considerations

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

## 🧪 Testing

This package includes comprehensive Artisan commands for testing functionality within your Laravel application:

```bash
# Test all progressive enhancement levels
php artisan agent:test-all-levels "What can you do?"

# Test individual levels
php artisan agent:test-level1 "Hello"
php artisan agent:test-level2 "What's the date?"
php artisan agent:test-level3 "I need help with pricing"
php artisan agent:test-level4 "Monitor system status"

# Test specific features
php artisan agent:test-streaming
php artisan agent:test-rag "What is Laravel?"
php artisan agent:test-voice-pipeline
php artisan agent:test-mcp-http
```

## 📚 Documentation

### Getting Started
- [Quick Start Guide](../QUICK_START.md) - Begin using the package in minutes
- [Progressive Enhancement](../PROGRESSIVE_ENHANCEMENT.md) - Understanding the 4-level architecture
- [Configuration Reference](../CONFIGURATION.md) - Complete configuration options

### Core Features
- [Tools Documentation](../TOOLS.md) - Creating and using tools
- [RAG Guide](../RAG_GUIDE.md) - Retrieval-Augmented Generation
- [Handoff Documentation](../HANDOFF.md) - Agent-to-agent handoffs
- [MCP Documentation](../MCP.md) - Model Context Protocol

### Advanced Features
- [Voice Documentation](../VOICE.md) - Audio transcription and text-to-speech
- [Streaming Documentation](../STREAMING.md) - Real-time responses
- [Tracing Documentation](../TRACING.md) - Debugging and monitoring
- [Lifecycle Management](../LIFECYCLE_MANAGEMENT.md) - Resource optimization

### Integration
- [Model Providers](../MODEL_PROVIDERS.md) - Using alternative AI providers
- [Visualization](../VISUALIZATION.md) - Visualizing agent interactions
- [Guardrails Documentation](../GUARDRAILS.md) - Input/output validation and transformation

## 🤝 Contributing

We welcome contributions to the OpenAI Agents for Laravel package! Please see our [Contributing Guide](../CONTRIBUTING.md) for details on how to get started.

## 📄 License

The OpenAI Agents for Laravel package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
