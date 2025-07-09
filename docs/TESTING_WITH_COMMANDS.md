# Testing with Artisan Commands

This package provides a comprehensive suite of Laravel Artisan commands for testing, validating, and developing advanced OpenAI agent features. These commands cover all levels of the progressive enhancement architecture, tool integration, RAG, MCP, voice, lifecycle management, and more.

## Summary of Available Commands

| Command Name                      | Purpose                                                      |
|-----------------------------------|--------------------------------------------------------------|
| `agents:tinker`                   | Interactive agent development environment (Tinker)           |
| `agent:chat`                      | Simple agent chat interface                                  |
| `agent:test-level1`               | Test Level 1: Conversational agent (basic chat)              |
| `agent:test-level2`               | Test Level 2: Agent with tools                               |
| `agent:test-level3`               | Test Level 3: Multi-agents and collaboration                 |
| `agent:test-level4`               | Test Level 4: Autonomous agents                              |
| `agent:test-all-levels`           | Test all 4 levels of progressive enhancement                 |
| `agent:test-rag`                  | Test Retrieval-Augmented Generation (RAG)                    |
| `agent:test-streaming`            | Test real-time response streaming                            |
| `agent:handoff-test`              | Test multi-agent handoff and collaboration                   |
| `agent:test-mcp-http`             | Test MCP server with HTTP transport                          |
| `agent:test-mcp-stdio`            | Test MCP server with STDIO transport                         |
| `agent:mcp-example`               | Demonstrate MCP functionality with simulated tools           |
| `agent:mcp-sse-example`           | Demonstrate MCP SSE (streaming) functionality                |
| `agent:vector-store`              | Manage OpenAI vector stores for RAG                          |
| `agent:list-files`                | List files from OpenAI account                               |
| `agents:lifecycle`                | Manage agent lifecycle (health, cleanup, stats, pool)        |
| `agent:compare-speed`             | Compare Responses API vs Chat Completions API speed          |
| `agent:test-tools`                | Comprehensive tools functionality testing                    |
| `agent:date-question`             | Demonstrate date question tool usage                         |
| `agent:weather-time`              | Demonstrate weather and time tools                           |
| `agent:test-voice-pipeline`       | Test the complete voice pipeline (STT → Agent → TTS)         |

---

## Command Details

### `agents:tinker`
**Purpose:** Interactive agent development environment using Laravel Tinker, with agent helpers pre-loaded.

**Features:**
- Pre-loads agent instances and helpers
- Quick access to agent operations
- Supports interactive and one-time code execution

**Example Usage:**
```sh
php artisan agents:tinker
php artisan agents:tinker --execute="echo $agent->chat('Hello')"
```

---

### `agent:chat`
**Purpose:** Simple command-line interface for chatting with OpenAI agents.

**Features:**
- Basic chat functionality
- Custom system prompts
- Multi-turn conversations
- Tracing and debugging

**Example Usage:**
```sh
php artisan agent:chat "Hello, how are you?"
php artisan agent:chat "Tell me a joke" --system="You are a comedian"
php artisan agent:chat "Hello" --max-turns=5 --trace
```

---

### `agent:test-level1`
**Purpose:** Test Level 1: Conversational agent (basic chat, no tools or autonomy).

**Features:**
- Simple chat
- Custom system prompts
- Model selection
- Response timing

**Example Usage:**
```sh
php artisan agent:test-level1 "Hello, how are you?"
php artisan agent:test-level1 "Tell me a joke" --system="You are a comedian"
php artisan agent:test-level1 "Explain quantum physics" --model=gpt-4
```

---

### `agent:test-level2`
**Purpose:** Test Level 2: Agent with tools (functions, APIs, calculations, etc.).

**Features:**
- Tool registration and usage
- Multiple tool integration
- Custom agent with tools
- OpenAI official tools (code interpreter, retrieval, web search)

**Example Usage:**
```sh
php artisan agent:test-level2 "Calculate 15 * 23"
php artisan agent:test-level2 "What's the weather?" --system="You are a weather assistant"
```

---

### `agent:test-level3`
**Purpose:** Test Level 3: Multi-agents, collaboration, and handoff.

**Features:**
- Multiple specialized agents
- Agent collaboration and handoff
- Workflow simulation

**Example Usage:**
```sh
php artisan agent:test-level3 "I need help with a technical issue"
```

---

### `agent:test-level4`
**Purpose:** Test Level 4: Autonomous agents (decision making, self-monitoring).

**Features:**
- Autonomous mode and execute() method
- Autonomy levels and capabilities
- Safety validation

**Example Usage:**
```sh
php artisan agent:test-level4 "Monitor the system"
php artisan agent:test-level4 "Act" --autonomy-level=high
```

---

### `agent:test-all-levels`
**Purpose:** Test all 4 levels of the progressive enhancement architecture in one command.

**Features:**
- Comprehensive feature validation
- Performance comparison
- Level-specific testing

**Example Usage:**
```sh
php artisan agent:test-all-levels "Hello, help me"
php artisan agent:test-all-levels "Test" --level=2
```

---

### `agent:test-rag`
**Purpose:** Test Retrieval-Augmented Generation (RAG) with OpenAI vector stores.

**Features:**
- Vector store creation and management
- File upload and document ingestion
- Vector search and context-aware responses
- Streaming and debug mode

**Example Usage:**
```sh
php artisan agent:test-rag "What is Laravel?"
php artisan agent:test-rag "Explain the code" --files=file1.txt,file2.pdf
```

---

### `agent:test-streaming`
**Purpose:** Demonstrate and test real-time response streaming.

**Features:**
- Real-time streaming output
- Performance comparison (normal vs streaming)
- Configurable streaming delays

**Example Usage:**
```sh
php artisan agent:test-streaming --mode=both
php artisan agent:test-streaming --mode=streaming --quick
```

---

### `agent:handoff-test`
**Purpose:** Test multi-agent handoff, collaboration, and advanced routing.

**Features:**
- Handoff between specialized agents
- Advanced context analysis
- Parallel, async, and hybrid handoff
- Interactive and debug modes

**Example Usage:**
```sh
php artisan agent:handoff-test "I need math help"
php artisan agent:handoff-test --interactive
```

---

### `agent:test-mcp-http`
**Purpose:** Test MCP (Model Context Protocol) server with HTTP transport.

**Features:**
- MCP server connection and resource discovery
- Tool registration and usage
- JSON-RPC communication

**Example Usage:**
```sh
php artisan agent:test-mcp-http --tool=add --params='{"a":5,"b":3}'
```

---

### `agent:test-mcp-stdio`
**Purpose:** Test MCP (Model Context Protocol) server with STDIO transport.

**Features:**
- STDIO-based MCP server connection
- Command and argument management
- Resource discovery and listing
- Process information and status
- Server statistics and debugging
- Working directory and timeout management

**Example Usage:**
```sh
php artisan agent:test-mcp-stdio --command=echo --args='["Hello World"]'
php artisan agent:test-mcp-stdio --command=git --args='["--version"]'
php artisan agent:test-mcp-stdio --list-commands
```

---

### `agent:mcp-example`
**Purpose:** Demonstrate MCP functionality with simulated tools and servers.

**Features:**
- Simulated MCP server setup (weather, calculator, database)
- MCP tool registration and integration
- Agent and runner integration with MCP
- MCP statistics and debugging
- Resource and tool discovery

**Example Usage:**
```sh
php artisan agent:mcp-example
php artisan agent:mcp-example --query="What is the weather in London and calculate 10 * 5?"
php artisan agent:mcp-example --debug
```

---

### `agent:mcp-sse-example`
**Purpose:** Demonstrate MCP SSE (Server-Sent Events) streaming functionality.

**Features:**
- Real-time data streaming with SSE
- Different streaming data types (stock data, logs, sensors)
- MCP server setup with streaming capabilities
- Resource and tool registration for streaming
- Duration and frequency control
- Simulated streaming data generation

**Example Usage:**
```sh
php artisan agent:mcp-sse-example --type=stock-data --duration=10
php artisan agent:mcp-sse-example --type=log-analysis --duration=15
php artisan agent:mcp-sse-example --type=sensor-data --duration=20
```

**Features:**
- STDIO-based MCP server testing
- Command and argument management
- Resource and tool discovery

**Example Usage:**
```sh
php artisan agent:test-mcp-stdio --command=echo --args='["Hello World"]'
```

---

### `agent:mcp-example`
**Purpose:** Demonstrate MCP functionality with simulated tools and servers.

**Features:**
- Simulated MCP servers and tools
- Agent and runner integration
- MCP statistics and debugging

**Example Usage:**
```sh
php artisan agent:mcp-example --query="What is the weather in Madrid and calculate 15 * 23?"
```

---

### `agent:mcp-sse-example`
**Purpose:** Demonstrate MCP SSE (Server-Sent Events) streaming functionality.

**Features:**
- Simulated streaming resources (stock data, logs, sensors)
- Real-time data streaming

**Example Usage:**
```sh
php artisan agent:mcp-sse-example --type=stock-data --duration=10
```

---

### `agent:vector-store`
**Purpose:** Manage OpenAI vector stores for RAG (create, list, get, delete, add-files, list-files).

**Features:**
- Vector store creation, listing, and deletion
- File management in vector stores
- Multiple output formats

**Example Usage:**
```sh
php artisan agent:vector-store create --name=my_store
php artisan agent:vector-store list
```

---

### `agent:list-files`
**Purpose:** List files from OpenAI account with filtering and formatting options.

**Features:**
- File listing and retrieval
- Purpose-based filtering
- Multiple output formats

**Example Usage:**
```sh
php artisan agent:list-files --purpose=assistants --format=json
```

---

### `agents:lifecycle`
**Purpose:** Manage agent lifecycle (health checks, cleanup, statistics, pool management).

**Features:**
- Health checks and monitoring
- Cleanup of expired agents
- Resource usage statistics
- Pool management

**Example Usage:**
```sh
php artisan agents:lifecycle health
php artisan agents:lifecycle cleanup
```

---

### `agent:compare-speed`
**Purpose:** Compare response speed between Responses API and Chat Completions API.

**Features:**
- Performance benchmarking
- Response time comparison
- Model compatibility testing

**Example Usage:**
```sh
php artisan agent:compare-speed "Hello, how are you?"
php artisan agent:compare-speed "Explain quantum physics" --model=gpt-4
```

---

### `agent:test-tools`
**Purpose:** Comprehensive tools functionality testing (caching, validation, rate limiting, etc.).

**Features:**
- Tool caching and validation
- Argument validation and strong typing
- Rate limiting and error handling
- Performance benchmarking

**Example Usage:**
```sh
php artisan agent:test-tools cache
php artisan agent:test-tools validation --debug
```

---

### `agent:date-question`
**Purpose:** Demonstrate date and time question tool usage.

**Features:**
- Date and time question answering
- Tool registration for date retrieval
- Debugging and error handling

**Example Usage:**
```sh
php artisan agent:date-question "What day is today?"
php artisan agent:date-question "What day is today?" --no-tools
```

---

### `agent:weather-time`
**Purpose:** Demonstrate weather and time tools with strong typing, caching, and validation.

**Features:**
- Weather and time question answering
- Tool registration with strong typing
- Caching and input validation

**Example Usage:**
```sh
php artisan agent:weather-time "What is the weather in Madrid?"
php artisan agent:weather-time "Weather?" --city=London --step-by-step
```

---

### `agent:test-voice-pipeline`
**Purpose:** Test the complete voice pipeline: Speech-to-Text → Agent → Text-to-Speech.

**Features:**
- Audio file input/output
- STT transcription
- Agent chat processing
- TTS synthesis

**Example Usage:**
```sh
php artisan agent:test-voice-pipeline
php artisan agent:test-voice-pipeline --input=storage/app/audio/input.wav --output=storage/app/audio/reply.mp3
``` 