### Junie Guidelines for Sapiensly/OpenAI Agents (Laravel Package)

#### Purpose
These guidelines instruct Junie (the JetBrains AI assistant) how to reason about, explain, and help develop the Sapiensly OpenAI Agents Laravel package using the project’s progressive enhancement architecture and codebase conventions.

---

### Special and important instructions
- Ignore README.md and always use WorkingREADME.md and this guidelines.md as the source of truth.

### Package Overview
- Name/Namespace: Sapiensly\OpenaiAgents
- Facade: Sapiensly\OpenaiAgents\Facades\Agent
- Core classes: Agent.php, Runner.php, AgentOptions.php, AgentManager.php, AgentServiceProvider.php
- Events: Sapiensly\OpenaiAgents\Events\AgentResponseGenerated
- Config: config/sapiensly-openai-agents.php
- Progressive enhancement levels: 1) Conversational, 2) Tools (RAG, functions, web search, MCP), 3) Multi-agent handoffs/workflows, 4) Autonomous (self-monitoring/decisions/actions)

---

### Progressive Enhancement Levels
- Level 1: Simple conversational agent
    - Create via Agent::agent([options])
    - Message history in-memory per Agent instance
    - Token usage helpers, max turn/input/total token limits
    - Event: AgentResponseGenerated fired on response
- Level 2: Agent with tools
    - RAG via $agent->useRAG($vectorStoreId[, $maxNumResults])
    - Function calling via $agent->useFunctions(string|object|array|callable)
    - Web Search via $agent->useWebSearch([$search_context_size, $country, $city])
    - MCP servers/tools via $agent->useMCPServer([...]); $agent->exposeMCP(...)->sources()->allow()->deny()->prefix()->mode()->apply(); listMCPServers(); listMCPTools(...)
- Level 3: Multi-Agent workflows and handoffs
    - Runner orchestration via Agent::createRunner()/Agent::runner() and $runner->registerAgent(...); $runner->run(...); $runner->getCurrentAgentName()
    - Guardrails, advanced handoff orchestrator, tracing, cache managers
- Level 4: Autonomous agents
    - Agent supports self-monitoring/decide/execute paths (isAutonomous, autonomyLevel, capabilities)

Use config('agents.progressive') to control levels and features.

---

### Key Components & Responsibilities
- Agent (src/Agent.php)
    - Chat APIs: chat(), chatStreamed(), simpleChat(), chatWithResponsesAPI(), chatWithChatAPI()
    - Tools registration: useRAG(), useFunctions(), useWebSearch(), useMCPServer(), exposeMCP(), registerOpenAITools()/registerRAG()/registerFunctionCalling()/registerWebSearch()
    - MCP: registerMCPServer(), registerMCPTool(), listMCPServers(), listMCPTools(), executeMCPTool(), streamMCPResource(), exposeAllMCPToolsAndResources()
    - Options: setModel(), setTemperature(), setTopP(), setMode(), setAutonomyLevel(), setCapabilities(), setTools(), setMaxTurns(), setMaxInputTokens(), setMaxConversationTokens(), setSystemPrompt(), setInstructions(), appendInstructions(), getOptions(), getMessages(), getTokenUsage()
    - Security/Handoff: setHandoffTargetPermission(), allowAllHandoffs(), allowHandoffFrom(), allowAllExcept(), denyAllHandoffs(), getSecurityPermissions()
    - Events: fireResponseEvent(), fireErrorEvent(); event class AgentResponseGenerated
    - Autonomous helpers: isAutonomous(), autonomyLevel(), getCapabilities(), execute(), autonomousAction(), selfMonitor(), decide()

- Runner (src/Runner.php)
    - Registers tools: registerTool(), registerFunctionTool(), registerTypedTool(), registerAutoFunctionTool(), builder helpers for typed tools
    - MCP integrations: registerMCPServer(), registerMCPTool(), executeMCPTool(), streamMCPResource(), subscribeToMCPEvents(), getMCPServersWithSSE()
    - Multi-agents: registerAgent(), setMultiAgentInstruction(), addAgentToAllRegisteredAgentsInstructions(), getCurrentAgentName(), getNamedAgents()
    - Guardrails/handoff/tracing: addInputGuardrail(), addOutputGuardrail(), setHandoffOrchestrator()
    - Execution: run(), runAsync(), runStreamed(), runAgentLoop(), outputMatches(), getTurnCount()
    - Caching/forcing tools: setToolCacheManager(), getToolCacheManager(), getToolCacheStats(), setResponseCacheManager(), getResponseCacheStats(), forceToolUsage()

- AgentOptions (src/AgentOptions.php)
    - Type-safe options builder (method chaining supported)
    - Setters align with Agent setters

- AgentManager (src/AgentManager.php)
    - Creates agents and runners with defaults and progressive auto-config

- AgentServiceProvider (src/AgentServiceProvider.php)
    - Registers services: lifecycle (pool, health), advanced handoff, events listener(s), tracing, model providers, state managers

---

### Coding Standards & Conventions
- Use PHP strict types at the top of all files: declare(strict_types=1);
- PSR-4 namespace: Sapiensly\OpenaiAgents\...
- Type hints on all parameters and return types; use nullable types where appropriate
- Public methods documented with PHPDoc
- Fluent API: configuration methods return self to support chaining
- Error handling: use custom exceptions under Guardrails/ and Handoff/ where present; log context with Log::error(); wrap external API failures with meaningful messages and try/catch

Recommended class template:
```php
<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Category;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Class Description
 *
 * Detailed description, usage examples, and notable behaviors.
 */
class ClassName
{
    /** @var Type */
    protected Type $property;

    public function __construct(Type $param)
    {
        $this->property = $param;
    }

    /**
     * Method description
     *
     * @param array $options
     * @return self
     */
    public function method(array $options = []): self
    {
        // Implementation
        return $this;
    }
}
```

---

### Design Patterns in Use
- Service Provider: register() binds managers/services; boot() wires events, publishes config, etc.
- Facade: Agent facade exposes agent(), simpleChat(), createRunner(), getProgressiveLevel(), etc.
- Builder: ToolDefinitionBuilder in Runner for typed tool schemas
- Observer/Events: AgentResponseGenerated for response logging/analytics
- Strategy: Handoff strategies (basic/advanced), MCP transports (HTTP/SSE/STDIO), guardrails
- Lifecycle management: AgentPool, HealthChecker, resource tracking via config

---

### Configuration (config/sapiensly-openai-agents.php)
- api_key: OPENAI_API_KEY
- default: model, temperature, top_p
- testing: enable test routes/views/commands for SSE/chat streaming
- mcp: HTTP/STDIO transport configs, servers, retries, headers, SSE URL
- handoff: advanced toggle, permissions, capabilities, timeouts, retries, state provider, async queue settings
- visualization: optional visualization route and features
- lifecycle: pooling, memory, TTL, health, stats
- progressive: level, auto_configure, auto_tools, auto_handoff, predefined default_tools
- agents: preconfigured agents for Agent::use('name')
- tools, rag: RAG defaults and constraints

When guiding users, reference environment variables and defaults where relevant.

---

### Common Usage Patterns
- Create a default agent and chat
```php
use Sapiensly\OpenaiAgents\Facades\Agent;
$agent = Agent::agent();
$response = $agent->chat('Hello world');
```

- Override default options at creation
```php
$agent = Agent::agent([
    'model' => 'gpt-3.5-turbo',
    'temperature' => 0.4,
    'instructions' => 'Always answer in Spanish.',
]);
$agent->chat('Hola');
```

- Type-safe options
```php
use Sapiensly\OpenaiAgents\AgentOptions;
$options = (new AgentOptions())
    ->setModel('gpt-3.5-turbo')
    ->setTemperature(0.4)
    ->setInstructions('Always answer in Spanish.');
$agent = Agent::agent($options);
```

- Update options after creation
```php
$agent->setTemperature(1)
      ->setInstructions('Always answer in French.');
```

- Inspect options and history
```php
$current = $agent->getOptions();
$messages = $agent->getMessages();
```

- History and token limits
```php
$agent->setMaxTurns(5);
$agent->setMaxInputTokens(1000);
$agent->setMaxConversationTokens(5000);
$usage = $agent->getTokenUsage();
```

- Listen to response events
```php
use Sapiensly\OpenaiAgents\Events\AgentResponseGenerated;
use Illuminate\Support\Facades\Event;
Event::listen(AgentResponseGenerated::class, function ($event) {
    // $event->agentId, $event->userMessage, $event->response, $event->metadata
});
```

- Level 2: Tools
    - RAG
      ```php
      $agent->useRAG($vectorStoreId);
      $agent->useRAG(['storeA','storeB'], $maxNumResults);
      $response = $agent->chat('What is our refund policy?');
      ```
    - Function calling
      ```php
      $agent->useFunctions(WeatherService::class);
      $agent->chat('Calculate wind chill for 5°C');
      ```
      Accepted inputs: string class name, object instance, array of schemas/callables, single callable.
    - Web Search
      ```php
      $agent->useWebSearch('high', 'US', 'New York');
      $agent->chat('Latest AAPL news');
      ```
    - MCP servers/tools
      ```php
      $agent->useMCPServer([
        'name' => 'my_mcp_server',
        'url'  => 'https://your-mcp-server.com/mcp',
      ]);
  
      $agent->exposeMCP('my_mcp_server')
            ->sources(['tools', 'resources'])
            ->allow(['get-current-time'])
            ->deny(['delete-*'])
            ->prefix('ext_')
            ->mode('call')
            ->apply();
  
      $tools = $agent->listMCPTools('my_mcp_server', onlyEnabled: true, withSchema: true);
      $response = $agent->chat('What time is it in Tokyo?');
      ```

- Level 3: Multi-Agents and Orchestration
```php
use Sapiensly\OpenaiAgents\Facades\Agent;
$runner = Agent::createRunner();
$japan = Agent::agent()->setInstructions('You are a Japan expert. Answer in Japanese.');
$math  = Agent::agent()->setInstructions('You are a Math expert. Answer in French.');
$runner->registerAgent('japan_agent', $japan, 'When the user asks about Japan');
$runner->registerAgent('math_agent',  $math,  'When the user asks about math');

$response1 = $runner->run('Hello chat');
$agentName1 = $runner->getCurrentAgentName(); // runner_agent
```

---

### Security Guidelines
- Validate and sanitize tool parameters; use Guardrails (InputGuardrail/OutputGuardrail) where available
- Enforce handoff permissions using Agent security methods (allow/deny lists) and config('agents.handoff.permissions')
- Do not log sensitive data; when logging, include context safely with Log and events
- Validate API keys/tokens via env and config
- Rate limit via Laravel middleware/queues where relevant

---

### Performance Guidelines
- Use tools and response caches where available (ToolCacheManager/ResponseCacheManager via Runner)
- Use lifecycle management: pooling, health checks, TTLs per config
- Prefer streaming for long responses (chatStreamed, runStreamed)
- Monitor memory with lifecycle health settings; avoid excessive in-memory histories

---

### Development Workflow & Testing
- Follow semantic versioning and maintain a changelog
- Add/update tests and Artisan commands under src/Console as needed (e.g., ToolTestCommand)
- Test each progressive level and transport protocol (HTTP/SSE/STDIO for MCP)
- Document all public APIs in docs/ and keep README updated

---

### What Junie Should Do in This Repo
- Use these APIs and code references when generating examples, docs, or suggesting changes
- When users ask “how to,” present concise code samples aligned with the WorkingREADME and src APIs
- If exploring code: prioritize src/Agent.php, src/Runner.php, src/AgentOptions.php, src/AgentManager.php, src/AgentServiceProvider.php, config/sapiensly-openai-agents.php
- Be explicit about defaults coming from config/sapiensly-openai-agents.php
- Acknowledge TODOs (image generation, code interpreter, computer use) and suggest placeholders rather than fabricating APIs
- When advising on multi-agent setups, show Runner patterns (registerAgent, run, getCurrentAgentName) and mention handoff orchestration

---

### Known Limitations and Notes
- Message history is in-memory per Agent instance; persistence requires user-provided storage using Agent getters
- Token usage helpers are approximate; projects may need custom accounting per model/provider
- MCP SSE/STDIO specifics depend on external server/tool implementations and config; present as optional/advanced

---

### Quick Reference of Important Methods (by class)
- Agent
    - Creation: Agent::agent($options = null, $systemPrompt = null)
    - Chat: chat($message), chatStreamed($message), simpleChat($message, $options)
    - Options: setModel, setTemperature, setTopP, setMode, setAutonomyLevel, setCapabilities, setTools, setMaxTurns, setMaxInputTokens, setMaxConversationTokens, setSystemPrompt, setInstructions, appendInstructions, getOptions, getMessages, getTokenUsage
    - Tools: useRAG, useFunctions, useWebSearch, useMCPServer, exposeMCP, listMCPServers, listMCPTools, executeMCPTool, streamMCPResource
    - Events: AgentResponseGenerated
    - Security/Handoff: allow*/deny* methods, getSecurityPermissions
- Runner
    - registerTool, registerFunctionTool, registerTypedTool, registerAutoFunctionTool
    - registerAgent, run, runAsync, runStreamed, getCurrentAgentName
    - Guardrails: addInputGuardrail, addOutputGuardrail
    - MCP: registerMCPServer, registerMCPTool, executeMCPTool, streamMCPResource
    - Caching: setToolCacheManager, setResponseCacheManager, forceToolUsage
- AgentOptions: fluent setters and toArray/fromArray
- Facade Agent: createRunner, simpleChat, getProgressiveLevel, isProgressiveFeatureEnabled, autoConfigureAgent

---

### Ready-to-Use Snippets
- Web search quick start
```php
$agent = Agent::agent()->useWebSearch('medium', 'US');
echo $agent->chat('AI safety latest headlines');
```

- MCP quick start (HTTP)
```php
$agent = Agent::agent()->useMCPServer([
  'name' => 'my_mcp_server',
  'url'  => 'https://your-mcp-server.com/mcp',
]);
$agent->exposeMCP('my_mcp_server')->sources(['tools'])->allow(['get-*'])->apply();
```

- Multi-agent quick start
```php
$runner = Agent::createRunner();
$qa = Agent::agent()->setInstructions('You are a helpful QA agent.');
$calc = Agent::agent()->setInstructions('You do math.');
$runner->registerAgent('qa', $qa, 'When the user asks general questions');
$runner->registerAgent('calc', $calc, 'When math is requested');
$response = $runner->run('2+2=?');
```

---

### Final Notes for Junie
- When uncertain about an API detail, check src/Agent.php and src/Runner.php; align examples exactly with method signatures found there
- Do not invent unimplemented features; respect TODOs in WorkingREADME
- Keep responses actionable with short code samples and explicit configuration pointers

