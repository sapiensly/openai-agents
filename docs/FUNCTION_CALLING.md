### What “Function calling” is in Sapiensly/OpenaiAgents

Function calling lets an Agent invoke your PHP functions/methods with structured parameters. The LLM decides when a function is needed; the Agent executes your PHP code and feeds the result back to the model so the final answer is grounded in your function’s output.

High-level loop:
- You register functions with the Agent via useFunctions(...)
- The Agent advertises these functions to the model as tool/function schemas
- When the model calls a function, the Agent:
  1) Parses the tool call, validates arguments
  2) Invokes your PHP callable
  3) Sends the function result back to the model
  4) Returns the model’s final answer and fires AgentResponseGenerated

This follows OpenAI-style tool use, wrapped in a Laravel-friendly API.

---

### Quick start (from WorkingREADME)

- Lines 120–125 in WorkigREADME.md:
```php
use Sapiensly\OpenaiAgents\Example\AI\WeatherService; // included as an example
$agent->useFunctions(WeatherService::class);
$response = $agent->chat('Calculate wind chill factor for a temperature of 5°C');
```
What happens:
- The Agent inspects WeatherService’s public methods and exposes them as callable tools with structured params.
- When you ask for wind chill, the model selects the matching method, the Agent runs it, returns the result to the model, and you receive a natural-language answer.

---

### Accepted inputs for useFunctions

useFunctions method accepts:
- String (FQCN): A class name. The Agent instantiates the class and exposes its public methods as functions.
- Object instance: The Agent inspects the instance’s class and exposes its public methods.
- Array: An array of function schemas or callables; each callable is registered with a generated name.
- Callable: A single callable function registered with a generated name.

Examples:
- FQCN
```php
$agent->useFunctions(\App\Services\MathService::class);
$response = $agent->chat('Area of a circle with radius 3.5 cm?');
```
- Object instance (e.g., when you need configuration)
```php
$svc = new \App\Services\WeatherServiceV2(config('services.weather.api_key'));
$agent->useFunctions($svc);
$response = $agent->chat('Compute heat index for 30°C and 70% humidity');
```
- Single callable
```php
$agent->useFunctions(function (float $celsius): float { return $celsius * 9/5 + 32; });
$response = $agent->chat('Convert 23°C to Fahrenheit');
```
- Array of callables/schemas
```php
$agent->useFunctions([
  function (string $ticker): array { /* fetch quote */ return ['ticker' => $ticker, 'price' => 123.45]; },
  // or include pre-authored schemas if you’ve written them
]);
$response = $agent->chat('Get today’s price for AAPL');
```

---

### How schemas are built and used

- For classes/objects: the Agent scans public methods and builds function schemas (name, parameters, types) so the model knows how to call them.
- For callables: the Agent registers them with generated names and constructs a schema (based on reflection or defaults) so the model can supply arguments.
- The Agent bundles these schemas into the model request. If the model responds with a tool call, the Agent executes the mapped PHP callable and feeds results back to the model, which then produces the final user-facing message.

---

### When to use each registration form

- String (FQCN): Best for service classes with multiple useful methods (WeatherService, MathService).
- Object instance: When you need a configured instance (e.g., API client with keys, custom base URLs).
- Callable: Great for small helpers or quick extensions without creating a class.
- Array: Compose multiple utilities at once (mix callables and any prebuilt schemas).

---

### Multi-turn, memory, and persistence

- In-memory message history is enabled by default and helps the model chain tool results across turns.
- You can control history and tokens:
  - setMaxTurns(n)
  - setMaxInputTokens(n)
  - setMaxConversationTokens(n)
- If you need persistence across requests (e.g., in a web app), use the persistence features (see docs/PERSISTENCE.md). Not required for basic function calling but useful for long-lived conversations.

---

### Guardrails, permissions, and safety

- Validate and sanitize tool parameters inside your functions (e.g., numeric ranges for weather calculations).
- Use guardrails (via Runner: addInputGuardrail, addOutputGuardrail) to enforce formats and constraints if orchestrating flows.
- Avoid logging secrets or raw user inputs; rely on AgentResponseGenerated for observability and scrub sensitive fields in logs.
- Wrap external API errors in meaningful messages; use try/catch and Log::error with safe context.

---

### Combining function calling with other tools

Function calling plays nicely with other Level 2 tools:
- RAG: `$agent->useRAG($vectorStoreId[, $maxNumResults]);`
- Web search: `$agent->useWebSearch([$search_context_size, $country, $city]);`
- MCP: `$agent->useMCPServer([...]); $agent->exposeMCP('name')->apply();`

Typical pattern: retrieve or search, then compute via your function; the model learns to pick the right tool based on your prompt and available toolset.

---

### Orchestration with Runner (advanced)

Runner enables multi-agent workflows and tool orchestration:
- Register function tools on specialized agents, then register those agents with a Runner.
- Useful APIs (from guidelines): registerFunctionTool, registerTypedTool, registerAutoFunctionTool, setToolCacheManager, setResponseCacheManager, forceToolUsage, run/runAsync/runStreamed.

Example:
```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$runner = Agent::createRunner();
$calc = Agent::agent()->useFunctions(\App\Services\CalcService::class);
$runner->registerAgent('calc', $calc, 'When math or numeric conversions are requested');

$response = $runner->run('Convert 5 kilometers to miles');
```

---

### Error handling patterns

- Defensive coding inside your functions (validate inputs; return clear, structured errors where helpful).
- Catch external API exceptions and rethrow domain errors or return safe fallbacks.
- Consider adding input guardrails to pre-validate tool calls when using Runner.

---

### Best practices

- Prefer deterministic, side-effect-light functions; they’re easier for the model to use reliably.
- Use descriptive method names and clear parameter names/types.
- Return structured, JSON-serializable data; the model will convert it into a friendly explanation.
- Start with a small set of tools; incrementally expand.
- Use Runner caches for expensive or rate-limited functions.
- If a tool must be used, guide via instructions and, in advanced flows, Runner’s forceToolUsage.

---

### End-to-end example

1) Define a service:
```php
namespace App\Services;

class WeatherService
{
    // Compute wind chill in °C given temp (°C) and wind speed (km/h)
    public function computeWindChill(float $temperatureC, float $windSpeedKmh): float
    {
        return 13.12 + 0.6215*$temperatureC - 11.37*pow($windSpeedKmh, 0.16)
             + 0.3965*$temperatureC*pow($windSpeedKmh, 0.16);
    }
}
```

2) Register and chat:
```php
use Sapiensly\OpenaiAgents\Facades\Agent;
use App\Services\WeatherService;

$agent = Agent::agent();
$agent->useFunctions(WeatherService::class);

echo $agent->chat('Calculate wind chill factor for 5°C with a 20 km/h wind');
```

3) Under the hood:
- The Agent exposes WeatherService::computeWindChill with typed params.
- The model calls it with structured args like { temperatureC: 5, windSpeedKmh: 20 }.
- The Agent executes your PHP method, returns the result to the model, and you receive a clear explanation such as: "The wind chill is approximately X°C." 

---

### Overview: Where “Function Calling” Lives in the Codebase

Function calling is primarily implemented inside the Agent class, supported by a schema-generation trait and some helper classes. At runtime, the Agent exposes your functions (as JSON schemas) to OpenAI, receives function_call outputs from the Responses API, invokes your PHP implementation, and then re-queries the model with the tool result to produce the final, natural-language answer.

Key modules and files:
- src/Agent.php — main orchestration for registering function tools, executing them, and running the Responses API loop
- src/Traits/FunctionSchemaGenerator.php — reflection-based schema generator for methods and callables
- src/AgentOptions.php — stores configuration and the tools array used when calling the model
- src/Tools/ToolArgumentValidator.php — JSON-schema-style argument validation for function tools
- src/Runner.php — optional orchestrator that can register tools, validate inputs, force tool usage, and integrate MCP tools as functions


### Core Control Flow in Agent

#### 1) Registering your functions as tools
- API: Agent::useFunctions(string|object|array|callable $functions) at src/Agent.php:1531
    - Steps under the hood:
        1) Build schemas from the input using generateFunctionSchema(...) (trait) — src/Agent.php:1534
        2) Register these schemas with the “tools” list via registerFunctionCalling(...) — src/Agent.php:1537 and 1094
        3) Register implementations (how to actually run the function) via registerImplementations(...) — src/Agent.php:1539 and 1583

- Schema generation: FunctionSchemaGenerator::generateFunctionSchema(...) at src/Traits/FunctionSchemaGenerator.php:19
    - Accepts the same types as useFunctions:
        - String (FQCN) => generateFromClass(...) reflects public methods — line 44
        - Object instance => generateFromClass(get_class(...)) — line 25
        - Callable => generateFromCallable(...) — line 62
        - Array => treated as already-a-schema (pass-through) — line 29

- For classes: generateFromClass(...) iterates public methods (Reflection) and builds function schemas with:
    - methodToSchema(...) — line 195
        - name: camelToSnake(method) — line 205
        - description: extractDescription(...) from docblock or method name — line 219
        - parameters: extractParameters(...) => JSON schema with types/required — line 239

- For callables: generateFromCallable(...) handles arrays [$obj,'method'], named functions, or closures — line 62; functionToSchema(...) — line 105.

- Registering schemas onto the Agent’s tools array: Agent::registerFunctionCalling(...) — src/Agent.php:1094
    - Validates each schema via isValidFunctionSchema(...) (in the trait) — line 299
    - Appends to $this->options->tools (AgentOptions) — lines 1104–1121 and final setTools call at 1121

- Registering implementations: Agent::registerImplementations(...) — src/Agent.php:1583
    - Class name string => instantiate class and call registerClassImplementations(...) — lines 1585–1593
    - Object => registerClassImplementations(...) — lines 1594–1599
    - Array => registerArrayImplementations(...) — line 1601
    - Callable => registerCallableImplementation(...) — lines 1604–1607

- Mapping schema names to concrete methods:
    - Agent::registerClassImplementations(object $instance, array $schemas) — src/Agent.php:1640
        - For each schema name (snake_case), it tries both camelCase and snake_case method names on your instance — lines 1645–1656
        - The implementation closure delegates to callMethodWithArgs(...) — lines 1648–1655
    - Agent::callMethodWithArgs(...) uses ReflectionMethod to map JSON args by name, filling defaults/nullables and throwing if a required param is missing — src/Agent.php:1614–1635
    - For arrays of definitions/callables: registerArrayImplementations(...) — src/Agent.php:1680–1692
    - For a single callable: registerCallableImplementation(...) — src/Agent.php:1698–1704

- Executing implementations by name: Agent::executeFunction(string $functionName, array $args = []) — src/Agent.php:2335
    - Looks up $this->functionImplementations[$functionName] and calls it — lines 2337–2342


#### 2) Sending tools to the model and handling function calls
- Primary chat loop: Agent::chat(...) delegates to chatWithResponsesAPI(...) — src/Agent.php:1735, 1773

- chatWithResponsesAPI(...) — src/Agent.php:1773–1968
    - Prepares messages/history and parameters
    - Tool management (important for function calling):
        - Starts with tools from options (what you registered via useFunctions) — lines 1830–1834
        - Merges any toolDefinitions passed by a Runner — lines 1836–1841
        - If MCP is active, adds MCP tools — lines 1843–1849
        - Deduplicates and normalizes each tool via normalizeToolForOpenAI(...) — lines 1851–1870 and 2391–2472
            - normalizeToolForOpenAI accepts a few shapes: already type=function + name, or Chat-style {type:function,function:{...}}, or MCP-ish {name, schema/parameters} — returns the format expected by the OpenAI Responses API
    - Calls $this->client->responses()->create($params) — line 1894
    - Interprets outputs:
        - If output->type === 'function_call', parse name and JSON arguments — lines 1906–1913
            - Execute the function: $this->executeFunction($functionName, $arguments) — line 1913
            - Append a ‘developer’ message summarizing tool call + result — lines 1916–1923
            - Then call chatWithResponsesAPI() again (recursively) to get the final natural-language answer that uses the tool result — lines 1925–1928
        - Otherwise, collect output_text content into the response string — lines 1938–1942
    - Emits AgentResponseGenerated event with analytics — line 1955

Note: There is legacy support code for extracting tool_calls in Chat Completions style (extractToolCalls(), ensureToolCallFormat(), etc., around 2098–2212). The current flow uses Responses API and function_call outputs, so that path is not hit for normal function calling with Responses API.


### AgentOptions: Where the tools live
- Location: src/AgentOptions.php
- Tools are stored in the tools property and exposed via get('tools') and setTools(...) — see setTools() at line 266 and get('tools') at 175.
- Defaults come from config('sapiensly-openai-agents.default_options.*'). The Agent reads $this->options->get('tools') inside chatWithResponsesAPI to send them to OpenAI.


### Argument Validation (Runner and Agent helper)
- File: src/Tools/ToolArgumentValidator.php
- Method: ToolArgumentValidator::validate(array $args, array $schema): array — returns a list of human-readable errors if args do not match JSON schema — lines 14–57
- Where used:
    - In Runner::run(...) tool invocation path when the model emits a [[tool:...]] pattern — see Runner::run, lines ~671–704 and validation in runAgentLoop at lines 987–999
    - In Agent::executeToolCall(...) when executing a tool definition passed into chat(...) (this path is available but typical use with Responses API function_call goes through executeFunction) — src/Agent.php:2222–2268, validator at 2234–2237


### Runner: Alternative orchestration and strong-typing helpers
- File: src/Runner.php
- Purpose: A higher-level orchestrator that can register tools (including MCP tools), pass them to the Agent, force tool usage, apply guardrails, and manage multi-turn/retries.
- Tool registration APIs:
    - registerFunctionTool(string $name, callable $fn, array $schema) — line 172
    - registerAutoFunctionTool(string $name, callable $fn) — reflects parameter types to auto-build a minimal schema — line 479
    - Strong-typed builders (ToolDefinition/ToolDefinitionBuilder): registerTypedTool(...) and builder helpers like registerStringTool/registerIntegerTool/... — lines 187–468
- Execution paths:
    - Standard: Runner::run($message) — builds toolDefs array and calls Agent::chat($input, $toolDefs,...). If the model produces a [[tool:...]] pattern in the response, Runner validates and executes it, feeds the result back, and continues — lines 599–800
    - Forced tool usage loop: Runner::runAgentLoop(...) — if you want to guarantee the model uses a function, even retrying with guidance — lines 965–1043


### MCP tools are function tools too
- If you use Agent::useMCPServer(...) and Agent::registerMCPTool(...) the Agent wraps MCP tools as function tools:
    - Agent::registerMCPTool(...) — src/Agent.php:1218–1262
        - Creates/gets MCPTool, then:
            - Adds function schema to tools via registerFunctionCalling([$tool->getSchema()]) — line 1251–1253
            - Registers an implementation mapping in $this->functionImplementations[$toolName] = fn($params) => $tool->execute($params) — lines 1255–1258
- Runner has analogous registration with registerMCPTool(...) — it registers a function tool with the MCPTool’s schema and its execute callback — src/Runner.php:221–235


### Name mapping and method resolution
- Schema names are snake_case (e.g., compute_wind_chill), per FunctionSchemaGenerator::camelToSnake() — src/Traits/...:205
- When invoking class methods, Agent tries both camelCase and snake_case to find your method — src/Agent.php:1645–1656
- Arguments are mapped by parameter name, with defaults/nullable handling in callMethodWithArgs(...) — src/Agent.php:1614–1635


### The end-to-end lifecycle
1) You call $agent->useFunctions(WeatherService::class)
    - FunctionSchemaGenerator reflects WeatherService public methods, builds schemas
    - Agent::registerFunctionCalling adds these schemas to options->tools
    - Agent::registerImplementations maps each schema name to a closure that calls your method
2) You run $agent->chat('Calculate wind chill...')
    - chatWithResponsesAPI sends instructions + message + tools to OpenAI Responses API
    - Model returns an output with type === 'function_call' (name + JSON args)
3) Agent executes your PHP function
    - executeFunction(...) looks up the implementation and calls it (with validated/mapped args)
    - The tool result is appended as a developer message
4) Agent calls the model again
    - The LLM reads the tool result and produces a final, friendly answer
    - AgentResponseGenerated event is fired with timing/tokens/metadata


### Error handling, limits, and events
- Missing implementation: executeFunction throws if function name is unmapped — src/Agent.php:2337–2341
- Missing required args: callMethodWithArgs throws InvalidArgumentException — src/Agent.php:1630–1631
- Token management and history trimming: getLimitedMessages(...) — src/Agent.php:2277–2328
- Event emitted each response: fireResponseEvent (invoked inside chatWithResponsesAPI) — src/Agent.php:1955; event class is Sapiensly\OpenaiAgents\Events\AgentResponseGenerated


### Practical notes and best practices
- Input types accepted by useFunctions: string FQCN, object instance, array of schemas/callables, or a single callable (as stated in WorkingREADME)
- Prefer explicit type hints and docblocks in your methods so the schema is informative, aiding model argument construction
- Return structured, JSON-serializable results from tools; the model will turn these into explanations
- If you need validation before execution, Runner’s ToolArgumentValidator path offers explicit JSON-schema checks
- If the model resists using tools, use Runner::forceToolUsage(...) to nudge/require tool calls, with retries


### Minimal code snippets tying it together
- Register class-based functions on Agent:
```php
use Sapiensly\OpenaiAgents\Facades\Agent;
use App\Services\WeatherService;

$agent = Agent::agent();
$agent->useFunctions(WeatherService::class);
$response = $agent->chat('Calculate wind chill for 5°C with a 20 km/h wind');
```

- Register a callable with Runner and force usage:
```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$runner = Agent::createRunner();
$runner->registerAutoFunctionTool('to_fahrenheit', function (float $c): float {
    return ($c * 9/5) + 32;
});
$runner->forceToolUsage(true, maxRetries: 3);
$response = $runner->run('Convert 23°C to Fahrenheit');
```

- Add validation with an explicit schema:
```php
$runner->registerFunctionTool('wind_chill', function (array $p) {
    $t = $p['temperature_c'];
    $v = $p['wind_speed_kmh'];
    return 13.12 + 0.6215*$t - 11.37*pow($v, 0.16) + 0.3965*$t*pow($v, 0.16);
}, [
    'type' => 'object',
    'properties' => [
        'temperature_c' => ['type' => 'number'],
        'wind_speed_kmh' => ['type' => 'number', 'minimum' => 0]
    ],
    'required' => ['temperature_c', 'wind_speed_kmh']
]);
```


### Quick mapping: files, classes, methods
- Agent (src/Agent.php)
    - useFunctions(...) — API entry to register functions
    - registerFunctionCalling(...), registerImplementations(...), registerClassImplementations(...), registerArrayImplementations(...), registerCallableImplementation(...)
    - callMethodWithArgs(...), executeFunction(...)
    - chat(...), chatWithResponsesAPI(...) — calls OpenAI, processes function_call, re-runs with tool result
    - normalizeToolForOpenAI(...), executeToolCall(...) [helper path for toolDefinitions]
- FunctionSchemaGenerator (src/Traits/FunctionSchemaGenerator.php)
    - generateFunctionSchema(...), generateFromClass(...), generateFromCallable(...)
    - methodToSchema(...), functionToSchema(...), extractParameters(...), extractFunctionParameters(...)
    - camelToSnake(...), snakeToCamel(...), isValidFunctionSchema(...)
- AgentOptions (src/AgentOptions.php)
    - setTools(...), get('tools') — persistent home for tool definitions
- ToolArgumentValidator (src/Tools/ToolArgumentValidator.php)
    - validate($args, $schema): array — optional JSON schema validation
- Runner (src/Runner.php)
    - registerFunctionTool(...), registerAutoFunctionTool(...), registerTypedTool(...)
    - run(...), runAgentLoop(...) — optional orchestration that passes toolDefs to Agent::chat and can enforce usage

This is the complete picture of how function calling is architected and executed in the package, from schema reflection through OpenAI interaction, local invocation, validation, and final response generation.