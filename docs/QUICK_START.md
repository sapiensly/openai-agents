# Quick Start Guide - Laravel OpenAI Agents

Get started with the Laravel OpenAI Agents package in minutes using our progressive enhancement approach.

## Installation

```bash
composer require sapiensly/openai-agents
```

## Configuration

### 1. Publish Configuration
```bash
php artisan vendor:publish --tag=config
```

### 2. Set Environment Variables
```env
OPENAI_API_KEY=your_openai_api_key_here
AGENTS_PROGRESSIVE_LEVEL=1
```

---

## Level 1: Conversational Agent

**Concept:**
- Basic agent for simple chat and Q&A.
- No tools, no autonomy, just conversation.

**Example:**
```php
use Sapiensly\OpenaiAgents\Agent;
$response = Agent::simpleChat('Hello world');
echo $response;
```
**Config:**
```php
'progressive' => [
    'level' => 1,
    'auto_configure' => true,
],
```

---

## Level 2: Agent with Tools

**Concept:**
- Agent can use tools (functions, APIs, calculations, file ops, etc).
- Still user-driven, but can perform actions.

**Example:**
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

**RAG (Retrieval-Augmented Generation):**
```php
// Setup RAG with vector store and files
$agent = Agent::create(['model' => 'gpt-4o']);
$agent->setupRAG('documentation', [
    'manual.pdf',
    'api-docs.txt'
], [
    'k' => 5,
    'r' => 0.8
]);

$response = $agent->chat('How does Laravel Eloquent work?');
```

**RAG Tools (Manual Setup):**
```php
$agent = Agent::create(['model' => 'gpt-4o']);

// Upload files
$fileResult = $agent->runTool('file_upload', 'upload', ['file_path' => 'manual.pdf']);
$fileData = json_decode($fileResult, true);
$fileId = $fileData['file_id'];

// Create vector store
$vsResult = $agent->runTool('vector_store', 'create', ['name' => 'docs']);
$vsData = json_decode($vsResult, true);
$vectorStoreId = $vsData['vector_store_id'];

// Add files to vector store
$agent->runTool('vector_store', 'add_files', [
    'vector_store_id' => $vectorStoreId,
    'file_ids' => [$fileId]
]);

// Enable RAG
$agent->enableRAG($vectorStoreId, ['k' => 5, 'r' => 0.8]);

$response = $agent->chat('Explain Laravel best practices');
```

**Config:**
```php
'progressive' => [
    'level' => 2,
    'auto_tools' => true,
    'default_tools' => ['calculator', 'date', 'rag', 'vector_store', 'file_upload'],
],
```

---

## RAG (Retrieval-Augmented Generation) - Nivel 2

RAG is available as a native tool in Level 2. Quick example:

```php
$agent = Agent::create(['model' => 'gpt-4o']);
// Usar un vector store existente por nombre o ID
$agent->useRAG('docs'); // o $agent->useRAG('vs_123...');
$response = $agent->chat('How does Laravel work?');
```

If the vector store doesn't exist, create it with setupRAG:

```php
$agent->setupRAG('docs', ['manual.pdf', 'api.txt']);
```

Comandos útiles:

```bash
php artisan agent:test-rag "What is RAG?" --files=manual.pdf
php artisan agent:vector-store list
```

For advanced flows, troubleshooting, and best practices, see [RAG_GUIDE.md](./RAG_GUIDE.md).

---

## Level 3: Multi-Agents

**Concept:**
- Multiple specialized agents collaborate (handoff, workflows).
- Each agent can have its own tools, persona, and config.

**Example:**
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

---

## Level 4: Autonomous Agents

**Concept:**
- Agents can decide, act, monitor, and learn autonomously.
- Not just reactive: can initiate actions, monitor systems, and adapt.
- New features: `mode`, `autonomy_level`, `capabilities`, `execute()`, self-monitoring, decision making.

**Example:**
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

## Testing & Development

### Quick Testing Commands

Test all levels at once:
```bash
php artisan agent:test-all-levels "What can you do?"
```

Test individual levels:
```bash
php artisan agent:test-level1 "Hello"
php artisan agent:test-level2 "What's the date?"
php artisan agent:test-level3 "What can you do autonomously?"
php artisan agent:test-level4 "What can you do autonomously?"
```

### Streaming Tests

Quick streaming test (1 turn, 30s timeout):
```bash
php artisan agent:test-streaming --quick
```

Controlled streaming test:
```bash
php artisan agent:test-streaming --turns=1 --timeout=10 --max-length=100
```

### Performance Comparison

Compare Responses API vs Chat Completions API:
```bash
php artisan agent:compare-speed "What is the capital of France?" --model=gpt-3.5-turbo
```

### Other Test Commands

```bash
# Weather and time tools
php artisan agent:weather-time "How's the weather in Madrid?"

# Date question with tools
php artisan agent:date-question "What day is today?"

# MCP (Model Context Protocol) testing
php artisan agent:test-mcp-http

# Voice pipeline testing
php artisan agent:test-voice-pipeline

# Tool testing
php artisan agent:test-tools

# RAG testing
php artisan agent:test-rag "How does Laravel work?" --files=manual.pdf,api-docs.txt

# Vector store management
php artisan agent:vector-store create --name=my_docs
php artisan agent:vector-store list
php artisan agent:vector-store get --id=vs_123
php artisan agent:vector-store add-files --id=vs_123 --files=file_abc,file_def
```

---

## Environment Configuration

### .env Settings for Each Level

#### Level 1: Conversational
```env
AGENTS_PROGRESSIVE_LEVEL=1
```

#### Level 2: With Tools
```env
AGENTS_PROGRESSIVE_LEVEL=2
AGENTS_AUTO_TOOLS=true
```

#### Level 3: Multi-Agents
```env
AGENTS_PROGRESSIVE_LEVEL=3
AGENTS_MULTI_AGENTS=true
AGENTS_AUTO_HANDOFF=true
```

#### Level 4: Autonomous
```env
AGENTS_PROGRESSIVE_LEVEL=4
AGENTS_AUTONOMY_ENABLED=true
AGENTS_ENTERPRISE_FEATURES=true
```

---

## API Architecture

### Responses API (Recommended)

This package uses OpenAI's **Responses API** as the primary interface, which provides:
- **Future-proof architecture** - OpenAI's recommended approach
- **Official tools support** - Code interpreter, retrieval, web search
- **Better performance** - Optimized for modern use cases
- **Simplified payloads** - Cleaner request/response format

### Chat Completions API (Legacy)

Still supported for function calling tools, but Responses API is preferred for new implementations.

---

This progressive enhancement approach allows you to start simple and scale up as your needs grow, without breaking existing functionality. 