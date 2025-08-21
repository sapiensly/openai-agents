## The Basics
Agent creation with default options (set in config/sapiensly-openai-agents.php)
```php
use Sapiensly\OpenaiAgents\Facades\Agent;

// Agent creation with default options (set in config/sapiensly-openai-agents.php)
$agent = Agent::agent();
$response = $agent->chat('Hello world');
```
You can override the default options at agent creation:
```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$agent = Agent::agent([
    'model' => 'gpt-3.5-turbo',
    'temperature' => 0.4,
    'instructions' => 'Always answer in Spanish.',
]);
$agent->chat('Hello world');
```
Use AgentOptions for a type-safe way to set options at agent creation:
```php
use Sapiensly\OpenaiAgents\AgentOptions;
use Sapiensly\OpenaiAgents\Facades\Agent;

$options = new AgentOptions()
    ->setModel('gpt-3.5-turbo')
    ->setTemperature(0.4)
    ->setInstructions('Always answer in Spanish.');
$agent = Agent::agent($options); 
$agent->chat('Hello world');
```
You can also change options after agent creation:
```php
$agent->setTemperature(1)->setInstructions('Always answer in French.');
$agent->chat('Hello world');
 ```
Check what options the agent is currently using:
```php
$agent->getOptions();
/* [
    "model" => "gpt-4o",
    ... // other options
  ]
*/
```

## Message History Management
> **💡 Tip**: Use in-memory history for simple scripts or single-session interactions. Use persistent history for web applications, APIs, or any scenario where conversations need to continue across multiple requests.

### In-Memory History (Default)
Automatic in-memory message history management enables multi-turn conversations:
```php
$agent->chat('What is the capital of France?');
$response = $agent->chat('What was my last question?');
echo $response; // Your last question was: "What is the capital of France?"
```
Set a custom limit for the message history:
```php
$agent->setMaxTurns(5); // Limit history to 5 user messages, default is 10 (set in config/sapiensly-openai-agents.php)
```
Use getMessages() to retrieve the current conversation's message history:
```php
$messages = $agent->getMessages();
// Returns: [['role' => 'user', 'content' => 'Hello'], ['role' => 'assistant', 'content' => 'Hi there!']]
```
You can also control token usage by setting a maximum token limit for the input and total tokens used in the conversation.

The provided token usage calculations may not exactly match your specific use case. You can implement custom logic to calculate token usage based on your requirements.

```php
$agent->setMaxInputTokens(1000); // Limit input tokens to 1000, default is 4096 (set in config/sapiensly-openai-agents.php)
$agent->setMaxConversationTokens(5000); // Limit total conversation tokens to 5000, default is 10,000 (set in config/sapiensly-openai-agents.php)
// Check current token usage
$tokenUsage = $agent->getTokenUsage();
// Returns: ['input_tokens' => 150, 'output_tokens' => 75, 'total_tokens' => 225]
```
### Persistent History (Optional)
For conversations that need to survive across requests (interact with an agent using API or UI), enable persistence.

Using the `withConversation()` method when creating an agent will create a unique conversation ID that can be used to resume the conversation later. Here is how to do it:
```php
// 1) Create an agent and opt-in to persistence
$options = new AgentOptions()
    ->setTemperature(0.4)
    ->setInstructions('Always answer in Spanish.');
$agent = Agent::agent($options)->withConversation(); // This creates a new conversation with a unique ID
// 2) Use as usual
$response = $agent->chat('Hello, my name is John');
// 3) Get the conversation id and agent id to resume later
$conversationId = $agent->getConversationId();
$agent_id = $agent->getId();
// if persistence is enabled, you will be able to continue the conversation later (e.g., in a different request)
$rebuilt_agent = Agent::load($agent_id)->withConversation($conversationId);
$response = $rebuilt_agent->chat('What did I say previously?');
```
## Response Event
Agent responses fire an event `AgentResponseGenerated` that you can listen to for logging or other purposes:
```php
use Sapiensly\OpenaiAgents\Events\AgentResponseGenerated;
use Illuminate\Support\Facades\Event;
Event::listen(AgentResponseGenerated::class, function ($event) {
    Log::info('Agent response: ' . $event->response);
});
```

## **Agent with Tools**
**Concept:** Agent can use tools (retrieval, functions, APIs, etc).

1. RAG (Retrieval-Augmented Generation)—Allows agents to retrieve relevant documents from a knowledge base.
```php
use Sapiensly\OpenaiAgents\Facades\Agent
$agent = Agent::agent();
$agent->useRAG($vectorStoreId); // $vectorStoreId is ID or name of an existing vector store in your OpenAI account. Array of vector store IDs is supported.
$agent->useRAG($vectorStoreId, $maxNumResults); // Optional: specify max number of results to return, default set in config/sapiensly-openai-agents.php
$response = $agent->chat('What is our refund policy?');
```
TODO: Document how to create and manage vector stores and files in OpenAI.

2. Function calling—Allows agents to call functions with structured parameters.
```php
use Sapiensly\OpenaiAgents\Example\AI\WeatherService; //included as an example
$agent->useFunctions(WeatherService::class);
$response = $agent->chat('Calculate wind chill factor for a temperature of 5°C');
```
The `useFunctions` method accepts four different parameter types:
- **String**: Fully qualified class name that exists in the application. The method will instantiate the class and generate function schemas from its public methods.
- **Object**: An instance of a class. The method will extract the class name and generate function schemas from its public methods.
- **Array**: An array of function schemas or callables. The method will register each callable with a generated name.
- **Callable**: A single callable function. The method will register it with a generated name.

3. Web Search—Allows agents to search the web for recent information.
```php
$agent->useWebSearch();
$response = $agent->chat('Search in web latest news fo AAPL stock');
```
You can customize web search behavior by passing optional parameters to the `useWebSearch` method:
```php
$agent->useWebSearch($search_context_size); // $search_context_size: The desired search context size. Valid options: 'high', 'medium', 'low'. Default is medium.
$agent->useWebSearch($search_context_size, $country); // $country: The optional country for approximate user location must be a two-letter ISO format.
$agent->useWebSearch($search_context_size, $country, $city); // $city Optional city for approximate user location.
```

4. MCP servers and tools—Allows agents to use MCP servers and tools for specialized tasks outside the codebase.
Here is a basic example of how to set up and use an HTTPS MCP server with tools:
```php
// 1. Tell the agent to use an MCP server (you can pass an array of servers if needed)
$agent->useMCPServer([
    'name' => 'my_mcp_server', // Name of your MCP server
    'url' => 'https://your-mcp-server.com/mcp', // URL of your MCP server
]);
// 2. Expose tools that the agent can use from the MCP server
// You can expose all tools and resources available on the MCP server
$agent->exposeMCP('my_mcp_server');
// Or, alternatively, you can use filters to expose only specific tools or resources
$agent->exposeMCP('my_mcp_server')
    ->sources(['tools', 'resources']) // 'tools' (JSON-RPC) TODO: Here check!
    ->allow(['get-current-time']) // ot ['get-*'] for wildcards
    ->deny(['delete-*']) // or ['*password*'] for wildcards
    ->prefix('ext_') // Optional prefix for tool names to avoid naming conflicts
    ->mode('call') // or 'stream' | 'auto'
    ->apply();
// 3. Now you can use the agent to call tools from the MCP server
$response = $agent->chat('What is the current time in Tokyo?');
```
For SSE (Server-Sent Events) mode, you can set the server and handle streaming responses as follows:
```php
$agent = Agent::agent()->useMCPServer([
  'name' => 'my_mcp_server_with_sse',
  'url'  => 'https://your-mcp-server.com/mcp',
  'config' => [
    'sse_url' =>'url' => 'https://your-mcp-server.com/sse', // URL for SSE endpoint
  ]
]);
```
And then expose tools as before.

Check all the MCP servers and MCP tools you have configured for the agent:
```php
// List all configured MCP servers
$agent->listMCPServers();
// List all exposed MCP tools
$agent->listMCPTools(); // returns an array of all tools names from all configured servers
$agent->listMCPTools('my_mcp_server'); // returns and array of tools names for the specified server name
$agent->listMCPTools('my_mcp_server', onlyEnabled: true, withSchema: true); // returns an array of enabled tools names with their schemas for the specified server name
```
TODO: Image generation
TODO: Code Interpreter
TODO: Computer use

### **Level 3: Multi-Agents**
**Concept:** Multiple specialized agents collaborate (handoff, workflows).
```php
use Sapiensly\OpenaiAgents\Facades\Agent;
// Create a runner to orchestrate multiagent execution
$runner = Agent::runner(); 
// As a simplified example, let's create two agents with different instructions
$japan_agent = Agent::agent()->setInstructions("You are expert in Japan. Always answer in Japanese.");
$math_agent = Agent::agent()->setInstructions("You are expert in Math. Always answer in French");
// Now we can register these agents with the runner by passing their names, the agent instances, and instructions on when to use them
$runner->registerAgent('japan_agent', $japan_agent, 'When the user asks about Japan');
$runner->registerAgent('math_agent', $math_agent, 'When the user asks about math');
// Now we can run the runner with a conversation to see how it works
$response1 = $runner->run("Hello chat"); // the answer will be in English
$response1_agent = $runner->getCurrentAgentName(); // 'runner_agent', this is the default name for the runner agent
$response2 = $runner->run("What is the best result Japan has achieved in a World Cup?"); // the answer will be in Japanese
$response2_agent = $runner->getCurrentAgentName(); // 'japan_agent'
$response3 = $runner->run("In advanced Math, what is a Fourier Transformation"); // the answer will be in French
$response3_agent = $runner->getCurrentAgentName(); // 'math_agent'
$response4 = $runner->run("Thank you. Do you know what is Laravel?"); // the answer will be in English, as no specialized agent is registered for Laravel
$response4_agent = $runner->getCurrentAgentName(); // 'runner_agent'
```
