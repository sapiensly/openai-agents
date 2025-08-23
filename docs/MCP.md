### MCP in Sapiensly\OpenaiAgents (Unified Guide)

This single document explains, from simple to advanced, how to enable and use MCP (Model Context Protocol) within the Laravel package Sapiensly\OpenaiAgents. It consolidates quick-start examples, streamable setups, core concepts, configuration, and troubleshooting. Source of truth: WorkingREADME plus classes in packages/sapiensly/openai-agents/src/MCP.

---

### Quick start (SSE example)

```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$agent = Agent::agent()->useMCPServer([
  "name" => "time_sse",
  "url" => "https://mcp.higress.ai/mcp-time/cmdv51y3x003f9901loxkocyc",
  "config" => [
    "sse_url" => "https://mcp.higress.ai/mcp-time/cmdv51y3x003f9901loxkocyc/sse"
  ]
]);

$agent->exposeMCP("time_sse");
```

What this does:
- Registers an HTTP MCP server named time_sse and sets an explicit SSE endpoint for streaming.
- Exposes its Tools (JSON‑RPC) and Resources (REST) to the agent so they become callable functions when you chat with the agent.

Then simply:
```php
$response = $agent->chat('What time is it in Tokyo right now?');
```
The agent decides if/when to call an MCP tool and integrates the result in the final answer.

---

### Additional example: Streamable HTTP server (no explicit SSE URL)

You can register a plain HTTP MCP server without specifying a dedicated SSE URL. If the server advertises streaming capability and provides a default stream route, the agent can use streaming automatically when mode is set to auto.

```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$agent = Agent::agent()->useMCPServer([
  "name" => "mcp-time-http",
  "url" => "https://mcp.higress.ai/mcp-time/cmdv51y3x003f9901loxkocyc"
]);

// Expose both JSON-RPC tools and REST resources from the server
$agent->exposeMCP("mcp-time-http");

// Introspection helpers:
$agent->listMCPServers();
$agent->listMCPTools(onlyEnabled: true, withSchema: true);

```

Details:
- Exposes JSON‑RPC tools discovered via tools/list and calls them via tools/call.
- Discovers REST resources via GET /resources and calls via POST /call.
- With mode='auto', it streams if the resource URI suggests streaming (contains /sse or /stream) and the server supports SSE; otherwise it does a standard call.

Force stream for specific resources (if needed):
```php
$agent->exposeMCP('mcp-time-http')
  ->sources(['resources'])
  ->allow(['stream-time'])
  ->mode('stream') // force streaming
  ->apply();
```

---

### What is MCP in this package?

- MCP lets your agent use external capabilities (tools and resources) that are not in your codebase.
- Two main interaction modes:
  - JSON‑RPC “Tools”: list and call tool functions with typed arguments.
  - REST “Resources”: discovery and invocation of endpoints, including streamable endpoints via SSE.

---

### Key components (how it works)

- MCPServer (src/MCP/MCPServer.php)
  - Represents a server (name, URL, transport, config, metadata, capabilities).
  - Supports HTTP (default) and stdio (optional).
  - Uses MCPClient for HTTP.

- MCPClient (src/MCP/MCPClient.php)
  - HTTP client that implements:
    - GET /resources (discovery)
    - POST /call (invoke resource)
    - GET /info (server info + capabilities)
    - GET /stats (server stats)
    - JSON‑RPC: POST base URL with method tools/list and tools/call
    - Streaming via SSE: GET/POST to stream URL (default /stream or full_stream_url) and event subscription via /events

- MCPManager (src/MCP/MCPManager.php)
  - Orchestrates servers, resources, tools; maintains stats; executes tools and streams.

- MCPTool (src/MCP/MCPTool.php)
  - Wraps an MCP resource (REST or JSON‑RPC proxy) as a tool the agent can call.
  - Validates parameters against schema, executes call or stream.

- UniversalExposeBuilder (src/MCP/UniversalExposeBuilder.php)
  - Chainable builder to expose Tools (JSON‑RPC) and Resources (REST) with filtering, prefixing, and execution mode selection.

- Agent (src/Agent.php) integration
  - useMCPServer(): register one or many servers.
  - exposeMCP(): expose server’s tools/resources as OpenAI function-calling tools.
  - listMCPServers(), listMCPTools(), executeMCPTool(), streamMCPResource(), debugMCPServer().

---

### Register MCP servers

Use the ergonomic helper useMCPServer:

```php
$agent->useMCPServer([
  'name' => 'my_mcp',
  'url'  => 'https://your-mcp-server.com/mcp',
  'config' => [
    // Common HTTP keys
    'headers' => [ 'Authorization' => 'Bearer XXX' ],
    'timeout' => 30,              // seconds
    'max_retries' => 3,
    'enable_logging' => false,

    // Endpoint paths or full URLs for REST/SSE
    'paths' => [
      'health' => '/health',
      'resources' => '/resources',
      'call' => '/call',
      'info' => '/info',
      'stats' => '/stats',
      'stream' => '/stream',
      'events' => '/events',
    ],
    'full_stream_url' => null,    // Full SSE URL override
    'sse_url' => null,            // Alias for full_stream_url; uses GET without JSON body
    'stream_method' => 'POST',    // 'GET' or 'POST' for streaming
    'stream_send_json_body' => true, // If POST, send JSON (true) or use query params (false)

    // Optional
    'enabled' => true,
    'metadata' => [],
    'capabilities' => ['sse','streaming'],
  ],
]);
```

Notes:
- sse_url is a convenient alias for full_stream_url and flips streaming to GET + no JSON body.
- supportsSSE() is derived from /info capabilities (e.g., sse or streaming).

STDIO (optional):
```php
'transport' => 'stdio',
'command' => 'path/to/binary',
'arguments' => ['--flag'],
'working_directory' => '/path',
'environment' => ['KEY' => 'VAL'],
'timeout' => 30,
'enable_logging' => false,
```

---

### Expose server Tools and Resources to the agent

Two approaches:

1) Quick (auto-apply)
```php
$agent->exposeMCP('my_mcp'); // exposes all tools and resources
```

2) Detailed (builder)
```php
$agent->exposeMCP('my_mcp')
  ->sources(['tools','resources']) // subset of ['tools','resources']
  ->allow(['get-*','time*'])       // allowlist patterns (fnmatch + substring)
  ->deny(['delete-*'])             // denylist patterns
  ->prefix('ext_')                 // avoid name collisions
  ->mode('auto')                   // 'auto' | 'call' | 'stream'
  ->apply();
```

What happens internally:
- Tools (JSON‑RPC):
  - Discover via tools/list and call via tools/call.
- Resources (REST):
  - Discover via GET /resources (if not known yet).
  - Register tools for each resource with mode selection:
    - mode='auto': stream if URI contains /sse or /stream and server supportsSSE(); otherwise call.

List exposed items:
```php
$agent->listMCPTools(); // tool names
$agent->listMCPTools('my_mcp', onlyEnabled: true, withSchema: true); // detailed with schema
```

---

### Using MCP in chat and directly

- In chat(), the agent uses function-calling to decide when to invoke exposed MCP tools.
- Execute a tool by name:
```php
$result = $agent->executeMCPTool('ext_get-current-time', ['timezone' => 'Asia/Tokyo']);
```
- Stream a specific resource (without LLM involvement):
```php
foreach ($agent->streamMCPResource('time_sse', 'stream-time', ['timezone' => 'UTC']) as $chunk) {
    // process $chunk in real-time
}
```
- Streaming with callback:
```php
$agent->streamMCPResourceWithCallback('time_sse', 'stream-time', ['timezone' => 'UTC'], function($chunk){
    // handle each event
});
```

---

### Practical scenarios

- Tools only (JSON‑RPC):
```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$agent = Agent::agent()->useMCPServer([
  'name' => 'calc',
  'url'  => 'https://your-mcp-jsonrpc.com/mcp',
]);

$agent->exposeMCP('calc')->sources(['tools'])->apply();

echo $agent->chat('Calculate 2 + 2 using your tools.');
```

- Resources only (REST) with auto streaming:
```php
$agent = Agent::agent()->useMCPServer([
  'name' => 'news',
  'url' => 'https://news.example.com/mcp',
  'config' => [ 'sse_url' => 'https://news.example.com/mcp/sse' ]
]);

$agent->exposeMCP('news')->sources(['resources'])->mode('auto')->apply();

$tools = $agent->listMCPTools('news', withSchema: true);
```

- Filters and prefix to avoid collisions:
```php
$agent->exposeMCP('my_mcp')
  ->allow(['get-*','list-*'])
  ->deny(['*secret*'])
  ->prefix('ext_')
  ->apply();
```

---

### Debugging and verification

- Registered servers:
```php
$agent->listMCPServers();                        // ['time_sse', 'calc', ...]
$agent->listMCPServers(onlyEnabled: true, verbose: true);
```

- Exposed tools:
```php
$agent->listMCPTools();
$agent->listMCPTools('time_sse', onlyEnabled: true, withSchema: true);
```

- Deep server diagnostics (HTTP + JSON‑RPC + optional streaming probe):
```php
$report = $agent->debugMCPServer('time_sse', [
  'probe' => ['tools','resources','capabilities'],
  'probe_stream' => true,
  'stream_max_events' => 3,
]);
```

- Programmatic stats and info:
  - MCPManager::getInfo(), getStats(), getStatistics()
  - MCPServer::getServerInfo(), getServerStats()
  - MCPClient::debug() for endpoint-by-endpoint probe

---

### Configuration reference (HTTP)

Supported config keys when registering servers (see MCPServer::__construct and MCPClient):

- headers: array of headers, e.g. Authorization
- timeout: int seconds for requests
- max_retries: retries for POST /call
- enable_logging: enable HTTP/stream logs
- paths: overrides for endpoints: health, resources, call, info, stats, stream, events
- full_stream_url: full SSE URL; if set, used instead of base+path
- sse_url: alias for full_stream_url; forces GET and no JSON body
- stream_method: 'GET' or 'POST' for streaming
- stream_send_json_body: for POST streaming, send JSON (true) or use query params (false)
- enabled: enable/disable the server
- metadata: arbitrary array stored in MCPServer
- capabilities: declared capabilities (e.g. ['sse','streaming'])

Manager configuration (MCPManager):
- enable_logging, auto_discover, connection_timeout, max_retries
- Managers are created lazily by Agent; you can adjust via Agent’s hooks where available.

---

### How execution mode is chosen (call vs stream)

- Builder logic (UniversalExposeBuilder::resolveMode):
  - If ->mode('call'|'stream') is set explicitly, it’s respected.
  - If mode='auto':
    - Checks if the resource URI contains /sse or /stream and confirms server supportsSSE().
    - If both true: uses 'stream'; else: 'call'.

- MCPTool::proxyFromDefinition also biases to 'stream' when server supports SSE or the URI looks streamy; otherwise it uses 'call'.

---

### Best practices

- Security: parameters are validated against schema in MCPTool::execute. Still, prefer least-privilege exposure with allow/deny filters.
- Logging: enable enable_logging in development for insight; keep off in production to reduce noise/cost.
- Performance: prefer streaming for long-running or incremental results; use call for short, atomic operations.
- Naming: use prefix() to avoid collisions when exposing across multiple servers.
- Compatibility: if a server doesn’t support tools/list or tools/call, the builder will keep going with REST resources.

---

### Quick reference: MCP on Agent

- useMCPServer(array|MCPServer): register servers
- exposeMCP(serverName[, filters, defaults]): expose tools/resources; returns a builder for chaining
- listMCPServers(onlyEnabled=false, verbose=false): list servers
- listMCPTools(serverNames=null, onlyEnabled=false, withSchema=false): list tools (optionally with schema)
- executeMCPTool(toolName, parameters=[]): run a tool directly
- streamMCPResource(serverName, resourceName, parameters=[]): iterate SSE events
- streamMCPResourceWithCallback(serverName, resourceName, parameters=[], callback): consume SSE with a callback
- debugMCPServer(serverName, options=[]): diagnostic report

---

### Troubleshooting

- I exposed MCP but no tools show up:
  - Check that exposeMCP('server')->sources([...])->apply() includes the right sources.
  - For JSON‑RPC tools, ensure tools/list is implemented and reachable.
  - For REST resources, ensure GET /resources is implemented and returns the expected shape.

- Streaming doesn’t start:
  - Make sure supportsSSE() is true. The server should report capabilities including sse or streaming via /info.
  - If your server uses a custom SSE path, set config['sse_url'].
  - For POST-based streaming servers, configure stream_method='POST' and stream_send_json_body appropriately.

- Calls fail intermittently:
  - Increase max_retries and check server logs.
  - Use debugMCPServer() to probe endpoints and examine responses.

---

### Wrap-up

With useMCPServer and exposeMCP, you can convert external MCP servers (both JSON‑RPC tools and REST resources, including streamable ones) into callable tools for your agent via OpenAI function-calling. Use filters, prefixes, and execution modes to control exposure and invocation; rely on listMCPTools, executeMCPTool, streamMCPResource, and debugMCPServer to verify and troubleshoot your MCP integrations end-to-end.