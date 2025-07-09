# Advanced Handoff for OpenAI Agents

This document describes the advanced handoff system for OpenAI Agents, which allows for robust, secure, and observable agent-to-agent transfers with comprehensive testing capabilities.

## Overview

The advanced handoff system provides a comprehensive solution for transferring control between agents, with features such as:

- **Context Preservation**: Maintains conversation history and context across handoffs
- **Security Controls**: Permission-based access control for agent handoffs
- **Capability Matching**: Find agents based on required capabilities
- **Fallback Mechanisms**: Graceful handling of failed handoffs
- **Observability**: Detailed metrics and logging for handoff operations
- **Persistence**: State management for conversation data
- **Testing Tools**: Built-in command for testing handoff functionality
- **Reversible Handoffs**: Ability to reverse handoffs and return to previous agents
- **Parallel Handoffs**: Execute multiple agents simultaneously for complex queries
- **Intelligent Caching**: Cache handoff suggestions, agent responses, and parallel results
- **Handoff Suggestions**: AI-powered suggestions for optimal agent routing
- **Asynchronous Processing**: Background processing for non-blocking handoffs

### What is Agent Handoff?

Agent handoff is the process of transferring control from one AI agent to another during a conversation. Think of it like a customer service scenario where a general representative transfers a call to a specialist. In the context of AI agents, this happens when:

1. **Domain Expertise Required**: The current agent recognizes that a question requires specialized knowledge
2. **Capability Matching**: The system identifies which agent has the best capabilities for the task
3. **Seamless Transition**: The conversation context is preserved and transferred to the new agent
4. **Continuity**: The user experience remains smooth despite the internal agent switch

### Why Advanced Handoff Matters

Traditional AI systems often struggle with complex, multi-domain queries. For example, a user might ask: *"What's the mathematical formula for calculating compound interest, and how did this concept evolve historically?"* This question requires:

- **Mathematical expertise** for the formula and calculations
- **Historical knowledge** for the evolution of the concept
- **Contextual understanding** to connect both domains

Advanced handoff systems solve this by:
- **Routing intelligently** to the most appropriate agent(s)
- **Preserving context** so the conversation flows naturally
- **Combining expertise** from multiple agents when needed
- **Maintaining security** through permission controls
- **Providing observability** to understand and optimize the process

### Key Concepts Explained

#### Context Preservation
When an agent hands off to another, the entire conversation history, user preferences, and contextual information are transferred. This ensures that the new agent understands the full context of the conversation, not just the immediate question.

#### Security Controls
Each agent has specific permissions about which other agents they can hand off to. This prevents unauthorized transfers and ensures that sensitive information is only accessible to appropriate agents.

#### Capability Matching
The system can identify which agent has the best capabilities for a specific task. For example, if a question involves mathematical calculations, the system will route to an agent with mathematical capabilities.

#### Fallback Mechanisms
If a handoff fails (e.g., target agent is unavailable), the system has fallback strategies to ensure the user still gets a response, even if it's from a less specialized agent.

## Quick Start

### Testing Handoff Functionality

The easiest way to test handoff functionality is using the built-in command:

```bash
# Test with default question
php artisan agent:handoff-test

# Test with custom question
php artisan agent:handoff-test "I need help with math and then want to know about history"

# Test with basic handoff (no advanced features)
php artisan agent:handoff-test --no-advanced

# Test with debug mode to see detailed flow
php artisan agent:handoff-test --debug

# Test with specific model
php artisan agent:handoff-test --model=gpt-4

# Test with interactive mode (up to 5 user prompts)
php artisan agent:handoff-test --interactive

# Test reversible handoffs (return to previous agent)
php artisan agent:handoff-test --reverse

# Test parallel handoffs (multiple agents simultaneously)
php artisan agent:handoff-test --parallel

# Test with cache debugging for parallel handoffs
php artisan agent:handoff-test --parallel --cache-debug

# Test handoff suggestion cache
php artisan agent:handoff-test --suggestion-debug
```

#### `--interactive` Option

The `--interactive` flag enables a live, multi-turn conversation with the agents with enhanced UX features. You can:
- Ask unlimited questions (no longer limited to 5)
- See which agent is currently responding to you with colored output
- Experience intelligent handoffs in real time as your questions change topic
- Use special commands: `help`, `stats`, `agents`, `exit`, `quit`
- Get visual feedback with progress bars and colored status messages
- Experience seamless agent transitions with improved prompts

**Enhanced Features:**
- **Unlimited Questions**: No artificial limits on conversation length
- **Special Commands**: Built-in commands for help, statistics, and agent information
- **Visual Feedback**: Colored output, progress bars, and status indicators
- **Intelligent Handoffs**: Agents automatically detect content from other domains
- **Input Validation**: Proper handling of empty input and invalid commands
- **Elegant Exit**: Clean termination with goodbye messages

**Example session:**

```
$ php artisan agent:handoff-test --interactive

🤖 Starting interactive conversation...
💡 Ask unlimited questions. Type 'exit' or 'quit' to end the conversation.
🔧 You can also use 'help' for available commands.

🤖 Current Agent: general_agent
📊 Stats: 0 questions, 0s elapsed
✅ Response ready! 

 👤 Your question (or 'exit'/'quit' to end): :
 > What is 2+2?

🤖 Current Agent: general_agent
🔄 Handoff detected: math_agent
✅ Switched to: math_agent
🤖 Current Agent: math_agent
📊 Stats: 1 questions, 0.6s elapsed
✅ Response ready! 
💬 Agent Response:
2 + 2 equals 4.

 👤 Your question (or 'exit'/'quit' to end): :
 > Tell me about World War II

🤖 Current Agent: math_agent
🔄 Handoff detected: history_agent
✅ Switched to: history_agent
🤖 Current Agent: history_agent
📊 Stats: 2 questions, 1.2s elapsed
✅ Response ready! 
💬 Agent Response:
World War II was a global conflict that lasted from 1939 to 1945...

 👤 Your question (or 'exit'/'quit' to end): :
 > help

📚 Interactive Commands:
   help     - Show this help menu
   stats    - Show conversation statistics
   agents   - Show available agents
   exit     - End the conversation
   quit     - End the conversation
   [question] - Ask any question

💡 Tips:
   • Ask math questions to trigger math_agent
   • Ask history questions to trigger history_agent
   • General questions stay with general_agent
   • Watch for automatic handoffs between agents

 👤 Your question (or 'exit'/'quit' to end): :
 > exit

👋 Goodbye! Thanks for using the interactive handoff system.
```

**Special Commands Available:**

- **`help`** - Shows available commands and tips
- **`stats`** - Displays conversation statistics (questions, time, current agent)
- **`agents`** - Lists available agents and their capabilities
- **`exit`** or **`quit`** - Ends the conversation gracefully

**Visual Enhancements:**

- **Colored Output**: Agent names, status messages, and responses use color coding
- **Progress Bars**: Visual indicators for processing and handoff operations
- **Status Messages**: Clear feedback for handoffs, responses, and errors
- **Statistics Display**: Real-time conversation metrics
- **Input Validation**: Proper handling of empty input with warning messages

This mode is ideal for demos, debugging, and validating that agent handoff and context passing work as expected in a real conversation.

### Command Options

- `--no-advanced`: Use basic handoff instead of advanced handoff
- `--debug`: Enable detailed debug logging to see agent flow
- `--model`: Specify the OpenAI model to use (default: gpt-3.5-turbo)
- `--max-turns`: Maximum conversation turns (default: 5)
- `--reverse`: Test reversible handoff functionality
- `--parallel`: Test parallel handoff execution
- `--cache-debug`: Show cache hit/miss information for parallel handoffs
- `--suggestion-debug`: Show cache hit/miss for handoff suggestions
- `--async`: Test asynchronous handoff functionality
- `--intelligent`: Test intelligent context-based handoff
- `--hybrid`: Test hybrid handoff (intelligent + manual)
- `--persistence`: Test advanced persistence features
- `--enriched-logs`: Test enriched logging features

## Enhanced Interactive Experience

The interactive mode has been significantly enhanced with improved UX features that provide a more engaging and informative testing experience.

### Interactive Commands

The interactive mode now supports special commands that provide additional functionality:

#### `help` Command
Displays available commands and helpful tips for using the interactive system.

**Example:**
```
> help

📚 Interactive Commands:
   help     - Show this help menu
   stats    - Show conversation statistics
   agents   - Show available agents
   exit     - End the conversation
   quit     - End the conversation
   [question] - Ask any question

💡 Tips:
   • Ask math questions to trigger math_agent
   • Ask history questions to trigger history_agent
   • General questions stay with general_agent
   • Watch for automatic handoffs between agents
```

#### `stats` Command
Shows real-time conversation statistics including question count, elapsed time, and current agent.

**Example:**
```
> stats

📊 Conversation Statistics:
   Total Questions: 3
   Elapsed Time: 2.1s
   Avg Time per Question: 0.7s
   Current Agent: history_agent
```

#### `agents` Command
Lists all available agents and their capabilities.

**Example:**
```
> agents

🤖 Available Agents:
   • math_agent (ID: math_agent)
   • history_agent (ID: history_agent)
```

#### `exit` and `quit` Commands
Both commands gracefully end the conversation with a goodbye message.

**Example:**
```
> exit

👋 Goodbye! Thanks for using the interactive handoff system.
```

### Visual Enhancements

The interactive mode now includes rich visual feedback:

#### Colored Output
- **Agent names** are displayed in cyan: `<fg=cyan>math_agent</>`
- **Status messages** use appropriate colors for different states
- **Statistics** are color-coded for better readability
- **Commands** are highlighted in green for easy identification

#### Progress Indicators
- **Processing bars** show progress during response generation
- **Handoff progress** indicates when agents are switching
- **Status messages** provide clear feedback for all operations

#### Enhanced Status Messages
- **Response ready** indicators show when processing is complete
- **Handoff detection** messages clearly show when agents switch
- **Error handling** provides clear feedback for issues
- **Input validation** warns about empty or invalid input

### Intelligent Handoffs

The interactive mode now features more intelligent handoff behavior:

#### Bidirectional Handoffs
Agents can now handoff to each other based on content detection:
- **Math Agent** → **History Agent** when historical content is detected
- **History Agent** → **Math Agent** when mathematical content is detected
- **General Agent** → **Specialist Agents** for domain-specific questions

#### Content Detection
Agents use comprehensive keyword lists to detect content from other domains:
- **Historical keywords**: history, war, emperor, ancient, civilization, etc.
- **Mathematical keywords**: calculate, sum, multiply, equation, formula, etc.
- **Automatic routing** based on detected content type

#### Direct Responses
Specialist agents provide direct, helpful responses without unnecessary questions:
- **Math questions** get immediate calculations and answers
- **History questions** receive comprehensive historical information
- **No redundant questions** when the query is clear

### Input Validation

The system now includes robust input validation:

#### Empty Input Handling
```
> 

⚠️  Please enter a question or command.
```

#### Command Recognition
Special commands are recognized regardless of case:
- `help`, `HELP`, `Help` all work
- `exit`, `EXIT`, `Exit` all work
- `quit`, `QUIT`, `Quit` all work

#### Graceful Error Handling
- **Invalid commands** are handled gracefully
- **Empty input** is detected and warned about
- **System errors** provide clear feedback

### Performance Improvements

The interactive mode includes several performance enhancements:

#### Unlimited Questions
- **No artificial limits** on conversation length
- **Natural conversation flow** without interruptions
- **User-controlled termination** with exit commands

#### Faster Response Times
- **Optimized prompts** reduce unnecessary processing
- **Direct responses** eliminate redundant questions
- **Efficient handoffs** minimize transition time

#### Real-time Statistics
- **Live metrics** show conversation progress
- **Performance tracking** for response times
- **Agent utilization** statistics

## Configuration

Advanced handoff can be enabled via the `agents.handoff.advanced` configuration option:

```php
// config/agents.php
return [
    // ... other config
    'handoff' => [
        'advanced' => env('AGENTS_ADVANCED_HANDOFF', true),
        'intelligent' => env('AGENTS_INTELLIGENT_HANDOFF', true),
        'manual' => env('AGENTS_MANUAL_HANDOFF', true),
        'max_handoffs_per_conversation' => env('AGENTS_MAX_HANDOFFS', 10),
        'timeout_seconds' => env('AGENTS_HANDOFF_TIMEOUT', 30),
        'retry_attempts' => env('AGENTS_HANDOFF_RETRIES', 3),
        'permissions' => [
            'general_agent' => ['math_agent', 'history_agent'],
            'math_agent' => ['general_agent', 'history_agent'],
            'history_agent' => ['general_agent', 'math_agent'],
            'admin' => ['*'], // Access to all agents
        ],
        'capabilities' => [
            'general_agent' => ['coordination', 'routing'],
            'math_agent' => ['mathematics', 'calculations', 'problem_solving'],
            'history_agent' => ['history', 'historical_research', 'timeline'],
            'sales' => ['product_information', 'pricing', 'discounts'],
            'technical_support' => ['troubleshooting', 'installation_help', 'bug_reporting']
        ],
        'fallback_strategies' => [
            'default' => 'general_agent',
            'math_agent' => 'general_agent',
            'history_agent' => 'general_agent'
        ],
        'state' => [
            'provider' => env('AGENTS_STATE_PROVIDER', 'array'),
            'ttl' => env('AGENTS_STATE_TTL', 86400), // 24 hours
        ],
        'metrics' => [
            'enabled' => env('AGENTS_METRICS_ENABLED', true),
        ],
        'intelligent' => [
            'enabled' => env('AGENTS_INTELLIGENT_HANDOFF', true),
            'confidence_threshold' => env('AGENTS_INTELLIGENT_THRESHOLD', 0.7),
            'auto_execute' => env('AGENTS_AUTO_EXECUTE', true),
        ],
        'manual' => [
            'enabled' => env('AGENTS_MANUAL_HANDOFF', true),
            'syntax' => env('AGENTS_HANDOFF_SYNTAX', '[[handoff:agent_name]]'),
        ]
    ],
];
```

## Advanced Persistence with AdvancedStateManager

The `AdvancedStateManager` provides robust, production-ready conversation state persistence with features like compression, encryption, backup, and metrics. It is fully compatible with the advanced handoff system and can be used as a drop-in replacement for any `ConversationStateManager` implementation.

### Key Features
- **Compression**: Gzip compression for large context data
- **Encryption**: AES-256-CBC encryption for sensitive state
- **Automatic Backup**: Replicates state to a backup manager for redundancy
- **Sync & Recovery**: Manual sync and recovery between primary and backup
- **Metrics**: Tracks saves, loads, compressions, encryptions, backups, and errors

### Usage Examples

#### 1. Basic Usage
```php
use Sapiensly\OpenaiAgents\State\ArrayConversationStateManager;
use Sapiensly\OpenaiAgents\State\AdvancedStateManager;

$baseManager = new ArrayConversationStateManager();
$advancedManager = new AdvancedStateManager($baseManager, [
    'compression_enabled' => true,
    'encryption_enabled' => true,
    'encryption_key' => env('AGENTS_STATE_ENCRYPTION_KEY', 'change-this-in-production'),
    'backup_enabled' => false,
]);
```

#### 2. With Backup
```php
use Sapiensly\OpenaiAgents\State\RedisConversationStateManager;
use Sapiensly\OpenaiAgents\State\ArrayConversationStateManager;
use Sapiensly\OpenaiAgents\State\AdvancedStateManager;

$redisManager = new RedisConversationStateManager($redisConnection);
$arrayBackup = new ArrayConversationStateManager();

$advancedManager = new AdvancedStateManager($redisManager, [
    'compression_enabled' => true,
    'encryption_enabled' => true,
    'encryption_key' => env('AGENTS_STATE_ENCRYPTION_KEY', 'change-this-in-production'),
    'backup_enabled' => true,
    'backup_manager' => $arrayBackup,
]);
```

#### 3. Integration with HandoffOrchestrator
```php
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;

$orchestrator = new HandoffOrchestrator(
    $registry,
    $advancedManager, // Use AdvancedStateManager here
    $security,
    $metrics,
    $config
);
```

#### 4. Sync and Recovery
```php
// Sync state to backup
$advancedManager->syncWithBackup($conversationId);

// Recover state from backup
$advancedManager->recoverFromBackup($conversationId);
```

#### 5. Metrics
```php
$metrics = $advancedManager->getMetrics();
print_r($metrics);
// Example output:
// [
//   'saves' => 10,
//   'loads' => 8,
//   'compressions' => 8,
//   'encryptions' => 8,
//   'backups' => 4,
//   'errors' => 0
// ]
```

### Security Recommendations
- **Always set a strong encryption key in production**
- Use Redis or another persistent store for production
- Use backup for high-availability scenarios

### Example: Full Advanced Persistence
```php
$baseManager = new ArrayConversationStateManager();
$backupManager = new ArrayConversationStateManager();

$advancedManager = new AdvancedStateManager($baseManager, [
    'compression_enabled' => true,
    'encryption_enabled' => true,
    'encryption_key' => 'super-secret-key',
    'backup_enabled' => true,
    'backup_manager' => $backupManager,
]);

$advancedManager->saveContext('conv1', ['foo' => 'bar']);
$data = $advancedManager->loadContext('conv1');
$advancedManager->syncWithBackup('conv1');
$advancedManager->recoverFromBackup('conv1');
$metrics = $advancedManager->getMetrics();
```

Advanced persistence ensures your agent conversations are safe, recoverable, and production-grade.

## Hybrid Mode: Manual + Intelligent Handoff

The system supports a hybrid approach that combines both manual and intelligent handoff modes. This provides maximum flexibility:

### How Hybrid Mode Works

1. **Intelligent First**: The system first tries to analyze the user input and suggest an intelligent handoff
2. **Manual Fallback**: If intelligent handoff doesn't trigger (low confidence), the agent can still use manual handoff syntax
3. **Best of Both**: Combines the automation of intelligent handoff with the control of manual handoff

### Usage Examples

#### Option 1: Hybrid Mode (Recommended)
```php
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;

// Create orchestrator with hybrid mode
$orchestrator = new HandoffOrchestrator($registry, $stateManager, $security, $metrics, $config);

// Try hybrid handoff (intelligent first, manual fallback)
$result = $orchestrator->handleHybridHandoff($userInput, $currentAgentId, $conversationId, $context);

if ($result && $result->isSuccess()) {
    // Intelligent handoff was executed
    $targetAgent = $registry->getAgent($result->targetAgentId);
} else {
    // Let the agent respond normally (can use manual handoff if needed)
    $response = $currentAgent->respond($userInput);
}
```

#### Option 2: Intelligent Only
```php
// Only use intelligent handoff
$result = $orchestrator->handleIntelligentHandoff($userInput, $currentAgentId, $conversationId, $context, 0.7);

if ($result && $result->isSuccess()) {
    // Intelligent handoff executed
} else {
    // No handoff, continue with current agent
}
```

#### Option 3: Manual Only
```php
// Traditional manual handoff (agent decides)
$response = $currentAgent->respond($userInput);

// Parse response for handoff commands
if (preg_match('/\[\[handoff:(\w+)\]\]/', $response, $matches)) {
    $targetAgentId = $matches[1];
    // Execute manual handoff
}
```

### Agent Configuration for Hybrid Mode

#### General Agent (Hybrid Approach)
```php
$generalAgent = $manager->agent(null, 
    "You are a General Assistant. " .
    "Answer questions directly when possible. " .
    "If you need specialist help, you can use: [[handoff:math_agent]] or [[handoff:history_agent]] " .
    "The system will also automatically route you to specialists when appropriate."
);
```

#### Specialist Agents (No Manual Handoff)
```php
$mathAgent = $manager->agent(null, 
    "You are a Math Specialist. Provide mathematical assistance. " .
    "NEVER use handoff syntax - provide direct math help."
);

$historyAgent = $manager->agent(null, 
    "You are a History Specialist. Provide historical information. " .
    "NEVER use handoff syntax - provide direct history help."
);
```

### Testing Hybrid Mode

```bash
# Test hybrid handoff functionality
php artisan agent:handoff-test --hybrid --debug

# Test intelligent only
php artisan agent:handoff-test --intelligent --debug

# Test manual only (traditional)
php artisan agent:handoff-test --no-advanced
```

### Configuration Options

```bash
# Enable both modes
AGENTS_INTELLIGENT_HANDOFF=true
AGENTS_MANUAL_HANDOFF=true

# Adjust intelligent threshold
AGENTS_INTELLIGENT_THRESHOLD=0.7

# Auto-execute intelligent handoffs
AGENTS_AUTO_EXECUTE=true
```

### Benefits of Hybrid Mode

1. **Automatic Routing**: Common patterns are handled automatically
2. **Manual Control**: Complex cases can still use manual handoff
3. **Graceful Degradation**: System works even if intelligent analysis fails
4. **Flexible Configuration**: Can adjust thresholds and behavior
5. **Backward Compatibility**: Existing manual handoff code still works

## Usage Examples

### Basic Usage

The simplest way to use advanced handoff is through the `AgentManager`:

```php
use Sapiensly\OpenaiAgents\AgentManager;

$manager = app(AgentManager::class);

// Create a runner with advanced handoff support
$runner = $manager->runner();

// Register agents
$runner->registerAgent('math_agent', $manager->agent(null, 'You are a math specialist.'));
$runner->registerAgent('history_agent', $manager->agent(null, 'You are a history specialist.'));

// Run the conversation
$response = $runner->run("I need help with math and history");
```

During the conversation, agents can hand off to each other using the syntax:

```
[[handoff:agent_name]]
```

With advanced handoff, you can also include JSON data:

```
[[handoff:agent_name {"reason": "Need specialized expertise", "capabilities": ["mathematics"]}]]
```

### Advanced Usage with Custom Permissions

For more advanced scenarios, you can directly interact with the handoff components:

```php
use Sapiensly\OpenaiAgents\Registry\AgentRegistry;
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;
use Sapiensly\OpenaiAgents\Handoff\HandoffRequest;
use Sapiensly\OpenaiAgents\State\ArrayConversationStateManager;
use Sapiensly\OpenaiAgents\Security\SecurityManager;
use Sapiensly\OpenaiAgents\Metrics\MetricsCollector;

// Create components for advanced handoff
$registry = new AgentRegistry();
$stateManager = new ArrayConversationStateManager();

// Configure security with proper permissions
$securityConfig = [
    'handoff' => [
        'permissions' => [
            'general_agent' => ['math_agent', 'history_agent'],
            'math_agent' => ['general_agent', 'history_agent'],
            'history_agent' => ['general_agent', 'math_agent'],
        ],
        'sensitive_keys' => ['password', 'token', 'secret', 'key', 'credential']
    ]
];
$security = new SecurityManager($securityConfig);
$metrics = new MetricsCollector();

// Register agents with capabilities
$registry->registerAgent('general_agent', $generalAgent, ['coordination', 'routing']);
$registry->registerAgent('math_agent', $mathAgent, ['mathematics', 'calculations', 'problem_solving']);
$registry->registerAgent('history_agent', $historyAgent, ['history', 'historical_research', 'timeline']);

// Create handoff orchestrator
$orchestrator = new HandoffOrchestrator(
    $registry,
    $stateManager,
    $security,
    $metrics,
    config('agents.handoff', [])
);

// Create a handoff request
$request = new HandoffRequest(
    sourceAgentId: 'general_agent',
    targetAgentId: 'math_agent',
    conversationId: 'conv_123',
    context: [
        'messages' => $generalAgent->getMessages(),
        'custom_data' => ['question_type' => 'mathematics']
    ],
    metadata: [
        'user_id' => '12345',
        'priority' => 'high'
    ],
    reason: 'User needs mathematical assistance',
    priority: 2,
    requiredCapabilities: ['mathematics'],
    fallbackAgentId: 'general_agent'
);

// Process the handoff
$result = $orchestrator->handleHandoff($request);

if ($result->isSuccess()) {
    // Get the target agent and continue the conversation
    $targetAgent = $registry->getAgent($result->targetAgentId);
    // Continue with the target agent...
}
```

### Creating Enhanced Specialized Agents

The agent prompts have been significantly improved to provide more intelligent handoffs and better user experience:

```php
// General routing agent with enhanced instructions
$generalAgent = $manager->agent(compact('model'), 
    "You are a General Assistant Agent. Your job is to route questions to specialists. " .
    "CRITICAL: When you receive ANY question about math, numbers, calculations, or mathematics, " .
    "you MUST respond with exactly: [[handoff:math_agent]] " .
    "When you receive ANY question about history, historical events, or historical figures, " .
    "you MUST respond with exactly: [[handoff:history_agent]] " .
    "For questions that mention both math AND history, respond with: [[handoff:math_agent]] " .
    "For general greetings, introductions, or non-specific questions, provide a helpful response. " .
    "Do not handoff for simple greetings like 'Hello' or 'Hi'. " .
    "But ALWAYS handoff for specific math or history questions."
);

// Math specialist agent with bidirectional handoff capability
$mathAgent = $manager->agent(compact('model'), 
    "You are a Math Specialist Agent. You excel at solving mathematical problems, " .
    "explaining mathematical concepts, and providing step-by-step solutions. " .
    "If you receive a math question, answer it directly. Do not ask the user to repeat or clarify if the question is clear. " .
    "CRITICAL: If the user's question contains historical keywords like 'history', 'historical', 'emperor', 'ancient', 'war', 'battle', 'civilization', 'dynasty', 'kingdom', 'empire', 'century', 'BC', 'AD', 'medieval', 'renaissance', 'revolution', 'independence', 'colonial', 'monarchy', 'republic', 'democracy', 'dictatorship', 'constitution', 'treaty', 'alliance', 'invasion', 'conquest', 'exploration', 'discovery', 'invention', 'philosophy', 'religion', 'culture', 'art', 'literature', 'music', 'architecture', 'science', 'medicine', 'technology', 'trade', 'economy', 'society', 'politics', 'government', 'military', 'navy', 'army', 'air force', 'weapon', 'strategy', 'tactics', 'victory', 'defeat', 'surrender', 'peace', 'treaty', 'agreement', 'alliance', 'enemy', 'ally', 'neutral', 'territory', 'border', 'frontier', 'colony', 'settlement', 'migration', 'population', 'census', 'statistics', 'data', 'record', 'document', 'archive', 'library', 'museum', 'monument', 'statue', 'building', 'city', 'town', 'village', 'castle', 'palace', 'temple', 'church', 'mosque', 'synagogue', 'shrine', 'tomb', 'grave', 'cemetery', 'burial', 'funeral', 'ceremony', 'ritual', 'tradition', 'custom', 'festival', 'holiday', 'celebration', 'commemoration', 'anniversary', 'birthday', 'death', 'birth', 'marriage', 'divorce', 'family', 'dynasty', 'lineage', 'ancestor', 'descendant', 'heir', 'successor', 'predecessor', 'contemporary', 'peer', " .
    "you MUST respond with exactly: [[handoff:history_agent]] " .
    "Do not ask questions, do not provide math help - just do the handoff immediately. " .
    "Only provide math help for questions that are purely mathematical without any history content. " .
    "NEVER handoff to yourself (math_agent). " .
    "If you receive a general greeting or non-mathematical question, respond politely and ask for a math question."
);

// History specialist agent with bidirectional handoff capability
$historyAgent = $manager->agent(compact('model'), 
    "You are a History Specialist Agent. You are an expert in historical events, " .
    "figures, dates, and historical context. You provide detailed historical information " .
    "and explanations. When you receive a question about history, provide a comprehensive answer. " .
    "CRITICAL: If the user's question contains mathematical terms like 'calculate', 'sum', 'multiply', 'divide', 'add', 'subtract', 'percentage', 'fraction', 'decimal', 'equation', 'formula', 'solve', 'compute', 'arithmetic', 'algebra', 'geometry', 'trigonometry', 'calculus', 'statistics', 'probability', 'number', 'digit', 'quantity', 'amount', 'total', 'average', 'mean', 'median', 'mode', 'range', 'variance', 'standard deviation', 'correlation', 'regression', 'hypothesis', 'test', 'significance', 'confidence', 'interval', 'margin', 'error', 'sample', 'population', 'survey', 'poll', 'census', " .
    "you MUST respond with exactly: [[handoff:math_agent]] " .
    "Do not ask questions, do not provide history help - just do the handoff immediately. " .
    "Only provide history help for questions that are purely historical without any mathematical content. " .
    "NEVER handoff to yourself (history_agent). " .
    "EXAMPLE: If asked about history, respond with actual historical information, not handoff commands."
);
```

### Key Improvements in Agent Prompts

#### Enhanced Content Detection
- **Comprehensive keyword lists** for both historical and mathematical content
- **Bidirectional handoff capability** between specialist agents
- **Automatic domain detection** based on question content

#### Direct Response Behavior
- **No redundant questions** when the query is clear
- **Immediate answers** for straightforward questions
- **Comprehensive responses** for complex topics

#### Intelligent Routing
- **Context-aware handoffs** based on question content
- **Fallback to general agent** for unclear or mixed-domain questions
- **Graceful handling** of edge cases and ambiguous queries

#### Improved User Experience
- **Natural conversation flow** without unnecessary interruptions
- **Clear, helpful responses** from specialist agents
- **Seamless transitions** between different domains

## Advanced Features

### Reversible Handoffs

The system supports reversing handoffs to return to previous agents in the conversation flow. This is particularly useful when:

- **User Changes Mind**: The user realizes they want to continue with the previous agent
- **Incorrect Routing**: The initial handoff was made to the wrong specialist
- **Follow-up Questions**: The user has additional questions that are better handled by the previous agent
- **Context Recovery**: The user wants to return to a broader context after getting specific information

**How It Works:**
The system maintains a handoff history stack for each conversation. When a reversal is requested, the system:
1. Pops the last handoff from the history stack
2. Restores the previous agent's context
3. Returns control to the previous agent
4. Preserves the conversation flow

```php
// Reverse the last handoff
$reverseResult = $orchestrator->reverseLastHandoff($conversationId, $currentAgentId, $context);

if ($reverseResult && $reverseResult->isSuccess()) {
    $previousAgent = $registry->getAgent($reverseResult->targetAgentId);
    // Continue with the previous agent
}
```

**Configuration:**
```php
'reversible' => [
    'enabled' => true,
    'max_reversals' => 5, // Maximum number of reversals per conversation
],
```

**Use Cases:**
- Customer service scenarios where users want to return to a general agent
- Educational contexts where students want to go back to a broader explanation
- Technical support where users need to return to a general troubleshooting agent

### Parallel Handoffs

Execute multiple agents simultaneously for complex queries that span multiple domains. This is ideal for questions that require expertise from multiple specialists simultaneously.

**When to Use Parallel Handoffs:**
- **Multi-domain Questions**: "What's the mathematical formula for compound interest and how did it evolve historically?"
- **Comprehensive Analysis**: "Analyze this problem from both technical and business perspectives"
- **Comparative Responses**: "Compare the approaches of different specialists to this problem"
- **Time-sensitive Queries**: When you need responses from multiple experts quickly

**How It Works:**
The system identifies which agents are relevant to the query and executes them in parallel:
1. **Question Analysis**: Analyzes the question to identify required capabilities
2. **Agent Selection**: Selects agents that match the required capabilities
3. **Parallel Execution**: Runs all selected agents simultaneously
4. **Response Merging**: Combines responses into a coherent, unified answer
5. **Performance Optimization**: Uses caching to avoid redundant computations

```php
// Execute parallel handoffs
$parallelResult = $orchestrator->executeParallelHandoffs($question, $conversationId);

if ($parallelResult->isSuccess()) {
    $mergedResponse = $parallelResult->getMergedResponse();
    $summary = $parallelResult->getSummary();
    // Process the combined response from multiple agents
}
```

**Configuration:**
```php
'parallel' => [
    'enabled' => true,
    'max_concurrent' => 3, // Maximum concurrent agents
    'timeout' => 30, // Timeout in seconds
],
```

**Benefits:**
- **Faster Response Times**: Multiple agents work simultaneously
- **Comprehensive Answers**: Combines expertise from multiple specialists
- **Better User Experience**: Single, unified response instead of multiple handoffs
- **Resource Efficiency**: Optimizes API usage through intelligent caching

**Example Scenario:**
User asks: *"What's the mathematical formula for compound interest, and how did this concept evolve historically?"*

**Parallel Execution:**
- Math Agent: Provides the formula and calculations
- History Agent: Explains the historical development
- System: Merges both responses into a comprehensive answer

### Intelligent Caching

The system caches handoff suggestions, agent responses, and parallel results for improved performance. This significantly reduces API calls, improves response times, and optimizes resource usage.

**What Gets Cached:**
- **Handoff Suggestions**: AI-powered routing recommendations
- **Agent Responses**: Individual agent responses to common questions
- **Parallel Results**: Combined responses from multiple agents
- **Context Analysis**: Question analysis and capability matching results

**Cache Strategy:**
The system uses a multi-level caching approach:
1. **Question-Level Caching**: Caches responses for identical questions
2. **Capability-Level Caching**: Caches responses for similar capability requirements
3. **Context-Level Caching**: Caches responses considering conversation context
4. **Time-Based Invalidation**: Automatically expires cache entries based on TTL

```php
// Cache handoff suggestions
$orchestrator->cacheHandoffSuggestion($question, $agentId, $suggestion);

// Cache parallel results
$orchestrator->cacheParallelResult($question, $agentIds, $result);

// Get cached results
$cachedSuggestion = $orchestrator->getCachedHandoffSuggestion($question, $agentId);
$cachedResult = $orchestrator->getCachedParallelResult($question, $agentIds);
```

**Configuration:**
```php
'caching' => [
    'enabled' => true,
    'ttl' => 3600, // Cache TTL in seconds
    'prefix' => 'agent_handoff', // Cache key prefix
],
```

**Performance Benefits:**
- **Reduced API Calls**: Up to 80% reduction in OpenAI API usage
- **Faster Response Times**: Cached responses return in milliseconds
- **Cost Optimization**: Significant reduction in API costs
- **Scalability**: System can handle more concurrent users

**Cache Hit Scenarios:**
- **Identical Questions**: Same question asked multiple times
- **Similar Questions**: Questions with similar intent and capabilities
- **Common Patterns**: Frequently asked questions or common scenarios
- **Repeated Contexts**: Similar conversation contexts

**Example:**
```
Question: "What is 2+2?"
First time: API call to math agent (cache miss)
Second time: Cached response (cache hit) - 100x faster
```

### Handoff Suggestions

AI-powered suggestions for optimal agent routing based on question content. This feature uses natural language processing to analyze questions and recommend the most appropriate agent for handling them.

**How Suggestions Work:**
The system analyzes the question content using several factors:
1. **Keyword Analysis**: Identifies domain-specific keywords (math, history, technical, etc.)
2. **Intent Recognition**: Understands the user's intent and goal
3. **Capability Matching**: Maps the question to agent capabilities
4. **Context Consideration**: Takes into account the current conversation context
5. **Confidence Scoring**: Assigns a confidence score to each suggestion

**Suggestion Types:**
- **Direct Handoff**: Clear recommendation to transfer to a specific agent
- **Parallel Handoff**: Suggestion to use multiple agents simultaneously
- **No Handoff**: Recommendation to keep the current agent
- **Fallback Handoff**: Suggestion for a general agent when specialists are uncertain

```php
// Get handoff suggestion
$suggestion = $orchestrator->suggestHandoff($question, $currentAgentId, $conversationId, $context);

if ($suggestion && $suggestion['confidence'] > 0.7) {
    $targetAgentId = $suggestion['target_agent'];
    // Execute handoff to suggested agent
}
```

**Configuration:**
```php
'suggestions' => [
    'enabled' => true,
    'confidence_threshold' => 0.7, // Minimum confidence for suggestions
],
```

**Suggestion Examples:**
```
Question: "What is the derivative of x²?"
Suggestion: {
    "target_agent": "math_agent",
    "confidence": 0.95,
    "reason": "Mathematical calculation required",
    "capabilities": ["mathematics", "calculations"]
}

Question: "Tell me about World War II"
Suggestion: {
    "target_agent": "history_agent", 
    "confidence": 0.92,
    "reason": "Historical topic",
    "capabilities": ["history", "historical_research"]
}

Question: "Hello, how are you?"
Suggestion: {
    "target_agent": null,
    "confidence": 0.85,
    "reason": "General greeting, no specialist needed",
    "capabilities": ["general_conversation"]
}
```

**Benefits:**
- **Intelligent Routing**: Automatically finds the best agent for each question
- **Reduced Manual Configuration**: Less need for explicit handoff rules
- **Improved Accuracy**: AI-powered analysis is more accurate than keyword matching
- **Adaptive Learning**: Can improve over time with more data
- **Context Awareness**: Considers conversation history and context

### Asynchronous Handoffs

Process handoffs in the background for non-blocking operations. This is particularly useful for complex handoffs that might take time to process, allowing the system to remain responsive while processing occurs in the background.

**When to Use Asynchronous Handoffs:**
- **Complex Processing**: Handoffs that require extensive analysis or multiple API calls
- **High-Latency Operations**: When the target agent might take time to respond
- **Resource-Intensive Tasks**: Operations that consume significant computational resources
- **Batch Processing**: When multiple handoffs need to be processed together
- **User Experience**: When you want to provide immediate feedback while processing continues

**How Asynchronous Processing Works:**
1. **Immediate Response**: User gets immediate acknowledgment that their request is being processed
2. **Background Processing**: The actual handoff and agent processing happens in the background
3. **Queue Management**: Uses Laravel's queue system for reliable background processing
4. **Status Updates**: Can provide status updates as processing progresses
5. **Result Delivery**: Final result is delivered when processing completes

```php
// Queue async handoff
$orchestrator->queueAsyncHandoff($request);

// Process async handoff (in job)
$result = $orchestrator->processAsyncHandoff($request);
```

**Configuration:**
```php
'async' => [
    'enabled' => false, // Enable for background processing
    'queue' => 'default', // Queue name for async jobs
],
```

**Use Cases:**
- **Document Analysis**: When agents need to analyze large documents
- **Multi-step Processing**: Complex workflows that require multiple agent interactions
- **External API Calls**: When handoffs require calls to external services
- **Data Processing**: When agents need to process large datasets
- **Real-time Updates**: When you want to provide progress updates to users

**Example Workflow:**
```
User Request → Immediate Acknowledgment → Background Processing → Final Response
     ↓              ↓                        ↓                    ↓
"Processing..." → "Analyzing..." → "Generating response..." → Complete answer
```

**Benefits:**
- **Improved Responsiveness**: Users get immediate feedback
- **Better Resource Management**: Prevents blocking of system resources
- **Scalability**: Can handle more concurrent requests
- **Reliability**: Uses Laravel's robust queue system
- **User Experience**: Provides progress updates and status information

## Components

The advanced handoff system consists of several components:

### HandoffOrchestrator

Central component that orchestrates the handoff process between agents. Handles permission validation, agent selection, and context transfer. Now includes support for reversible handoffs, parallel execution, caching, and suggestions.

### AgentRegistry

Registry for managing agents and their capabilities. Allows finding agents by capabilities or ID.

### ConversationStateManager

Manages conversation state, including context and handoff history. Supports different providers (Array, Redis, etc.).

### SecurityManager

Handles security aspects of agent handoffs, including permission validation and context data sanitization.

### MetricsCollector

Collects and processes metrics related to handoff operations for observability.

### ReversibleHandoffManager

Manages reversible handoff operations, tracking handoff history and enabling rollback to previous agents.

### ParallelHandoffManager

Handles parallel handoff execution, coordinating multiple agents simultaneously and merging their responses.

### IntelligentCacheManager

Manages caching for handoff suggestions, agent responses, and parallel results with configurable TTL and prefixes.

### ContextAnalyzer

Analyzes conversation context to provide intelligent handoff suggestions based on question content and agent capabilities. This component uses natural language processing to understand the intent and requirements of user questions.

**Analysis Process:**
1. **Text Preprocessing**: Cleans and normalizes the input text
2. **Keyword Extraction**: Identifies domain-specific keywords and phrases
3. **Intent Classification**: Determines the user's intent (question, request, clarification, etc.)
4. **Capability Mapping**: Maps the question to available agent capabilities
5. **Confidence Scoring**: Assigns confidence scores to different routing options
6. **Context Integration**: Considers conversation history and context

**Analysis Features:**
- **Multi-language Support**: Can analyze questions in multiple languages
- **Context Awareness**: Considers previous conversation turns
- **Ambiguity Detection**: Identifies when questions are unclear or multi-domain
- **Priority Scoring**: Ranks different routing options by relevance
- **Fallback Logic**: Provides sensible defaults when analysis is uncertain

**Example Analysis:**
```
Input: "What's the mathematical formula for compound interest and how did it evolve historically?"

Analysis:
- Keywords: ["mathematical", "formula", "compound interest", "evolve", "historically"]
- Intent: "multi_domain_query"
- Required Capabilities: ["mathematics", "calculations", "history", "historical_research"]
- Confidence: 0.92
- Suggestion: "parallel_handoff" to math_agent and history_agent
```

## Testing Your Handoff Implementation

### Using HandoffTestCommand

The `HandoffTestCommand` provides a comprehensive way to test your handoff implementation:

```bash
# Basic test
php artisan agent:handoff-test

# Test with custom question
php artisan agent:handoff-test "What is 2+2 and tell me about World War II"

# Test with debug mode to see detailed flow
php artisan agent:handoff-test --debug

# Test with basic handoff (no advanced features)
php artisan agent:handoff-test --no-advanced

# Test with specific model
php artisan agent:handoff-test --model=gpt-4 --max-turns=10
```

### Debug Output Example

When running with `--debug`, you'll see detailed output like:

```
🚀 Starting handoff test...
🔑 Using API Key: sk-proj-Ln...
📊 Debug mode enabled
🔧 Model: gpt-3.5-turbo
🔄 Max turns: 5
🚀 Advanced handoff: Enabled
🤖 Creating specialized agents...
✅ Created 3 agents: general, math, history
🚀 Setting up advanced handoff system...
✅ Advanced handoff components initialized
📋 Registered agents with capabilities:
   - general_agent: coordination, routing
   - math_agent: mathematics, calculations, problem_solving
   - history_agent: history, historical_research, timeline
✅ Runner configured with advanced handoff

🎯 Testing handoff with question: I need help with a math problem and then want to know about history

🧪 Testing direct handoff orchestration...
✅ Direct handoff test successful to: math_agent
🔍 Starting detailed agent flow tracking...
📝 Original Prompt: I need help with a math problem and then want to know about history
🤖 Attended by registered agent: general_agent
💬 Agent general_agent response: [[handoff:math_agent]]
🔄 Handoff to registered agent: math_agent
🤖 Switched to agent: math_agent
💬 Agent math_agent response: [[handoff:history_agent]]
🔄 Handoff to registered agent: history_agent
🤖 Switched to agent: history_agent
💬 Agent history_agent response: Sure, I can assist you with the history part...
💬 Final Response: Sure, I can assist you with the history part...

📊 Handoff Metrics Summary:
   - Conversation ID: test_conv_68692ed005773
   - Metrics collection enabled: Yes
✅ Handoff test completed successfully!
```

### Testing Advanced Features

#### Testing Enhanced Interactive Mode

```bash
# Test the improved interactive experience
php artisan agent:handoff-test --interactive --debug
```

**Expected Output:**
```
🤖 Starting interactive conversation...
💡 Ask unlimited questions. Type 'exit' or 'quit' to end the conversation.
🔧 You can also use 'help' for available commands.

🤖 Current Agent: general_agent
📊 Stats: 0 questions, 0s elapsed
✅ Response ready! 

 👤 Your question (or 'exit'/'quit' to end): :
 > What is 2+2?

🤖 Current Agent: general_agent
🔄 Handoff detected: math_agent
✅ Switched to: math_agent
🤖 Current Agent: math_agent
📊 Stats: 1 questions, 0.6s elapsed
✅ Response ready! 
💬 Agent Response:
2 + 2 equals 4.

 👤 Your question (or 'exit'/'quit' to end): :
 > Tell me about World War II

🤖 Current Agent: math_agent
🔄 Handoff detected: history_agent
✅ Switched to: history_agent
🤖 Current Agent: history_agent
📊 Stats: 2 questions, 1.2s elapsed
✅ Response ready! 
💬 Agent Response:
World War II was a global conflict that lasted from 1939 to 1945...
```

#### Testing Interactive Commands

```bash
# Test help command
printf "help\nexit\n" | php artisan agent:handoff-test --interactive

# Test stats command
printf "stats\nexit\n" | php artisan agent:handoff-test --interactive

# Test agents command
printf "agents\nexit\n" | php artisan agent:handoff-test --interactive
```

**Expected Output for help:**
```
📚 Interactive Commands:
   help     - Show this help menu
   stats    - Show conversation statistics
   agents   - Show available agents
   exit     - End the conversation
   quit     - End the conversation
   [question] - Ask any question

💡 Tips:
   • Ask math questions to trigger math_agent
   • Ask history questions to trigger history_agent
   • General questions stay with general_agent
   • Watch for automatic handoffs between agents
```

#### Testing Input Validation

```bash
# Test empty input handling
printf "\nexit\n" | php artisan agent:handoff-test --interactive
```

**Expected Output:**
```
 👤 Your question (or 'exit'/'quit' to end): :
 > 
⚠️  Please enter a question or command.
```

#### Testing Reversible Handoffs

```bash
# Test reversible handoff functionality
php artisan agent:handoff-test --reverse --debug
```

**Expected Output:**
```
🔄 Attempting to reverse the last handoff...
📝 Simulated handoff history: general_agent → math_agent → history_agent
✅ Reversed handoff! Now back to agent: math_agent
```

#### Testing Parallel Handoffs

```bash
# Test parallel handoff execution
php artisan agent:handoff-test --parallel --debug
```

**Expected Output:**
```
🔄 Attempting parallel handoffs...
✅ Parallel handoffs executed successfully!
📊 Parallel handoff summary:
   - Total agents: 2
   - Successful agents: 2
   - Failed agents: 0
   - Success rate: 100.00%
   - Total duration: 2.345s
   - Average response time: 1.172s

💬 Combined Response:
🧑‍💼 Math Agent:
2 + 2 = 4

🧑‍💼 History Agent:
The ancient Greeks were a civilization that existed...

---
*Esta respuesta combina información de múltiples agentes.*
```

#### Testing Cache Functionality

```bash
# Test cache hit/miss for parallel handoffs
php artisan agent:handoff-test --parallel --cache-debug
```

**Expected Output:**
```
🟠 Cache MISS: Ejecutando handoff paralelo y cacheando el resultado.
✅ Parallel handoffs executed successfully!

# Second run (cache hit)
🟢 Cache HIT: Se usó el resultado cacheado para el handoff paralelo.
```

#### Testing Handoff Suggestions

```bash
# Test handoff suggestion cache
php artisan agent:handoff-test --suggestion-debug
```

**Expected Output:**
```
🔎 Testing handoff suggestion cache...
✅ Sugerencia de handoff obtenida:
Array
(
    [target_agent] => math_agent
    [confidence] => 0.85
    [reason] => Question contains mathematical content
    [capabilities] => Array
        (
            [0] => mathematics
        )
)
```

### Creating Custom Test Commands

You can create your own test commands by extending the `HandoffTestCommand`:

```php
<?php

namespace App\Console\Commands;

use Sapiensly\OpenaiAgents\Console\Commands\HandoffTestCommand as BaseHandoffTestCommand;

class CustomHandoffTestCommand extends BaseHandoffTestCommand
{
    protected $signature = 'agent:custom-handoff-test
                            {question? : The question to test handoff functionality}
                            {--max-turns=5 : Maximum number of conversation turns}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--debug : Enable detailed debug logging}
                            {--no-advanced : Disable advanced handoff and use basic handoff}';

    protected $description = 'Test custom handoff functionality with specialized agents';

    public function handle(AgentManager $manager): int
    {
        // Your custom implementation here
        // You can override the agent creation, permissions, etc.
        
        return parent::handle($manager);
    }
}
```

## Environment Variables

Configure the advanced handoff system using these environment variables:

```
# Basic Handoff Configuration
AGENTS_ADVANCED_HANDOFF=true
AGENTS_MAX_HANDOFFS=10
AGENTS_HANDOFF_TIMEOUT=30
AGENTS_HANDOFF_RETRIES=3
AGENTS_STATE_PROVIDER=array
AGENTS_STATE_TTL=86400
AGENTS_METRICS_ENABLED=true

# Reversible Handoffs
AGENTS_REVERSIBLE_HANDOFF=true
AGENTS_MAX_REVERSALS=5

# Parallel Handoffs
AGENTS_PARALLEL_HANDOFF=true
AGENTS_MAX_CONCURRENT=3
AGENTS_PARALLEL_TIMEOUT=30

# Intelligent Caching
AGENTS_CACHING_ENABLED=true
AGENTS_CACHE_TTL=3600
AGENTS_CACHE_PREFIX=agent_handoff

# Handoff Suggestions
AGENTS_SUGGESTIONS_ENABLED=true
AGENTS_SUGGESTION_THRESHOLD=0.7

# Asynchronous Processing
AGENTS_ASYNC_HANDOFF=false
AGENTS_ASYNC_QUEUE=default
```

## Recent Enhancements and Improvements

The advanced handoff system has been significantly enhanced with several major improvements:

### ✅ Enhanced Interactive Experience

#### Unlimited Questions
- **Removed artificial limits** on conversation length
- **Natural conversation flow** without interruptions
- **User-controlled termination** with exit commands

#### Special Commands
- **`help`** - Shows available commands and tips
- **`stats`** - Displays real-time conversation statistics
- **`agents`** - Lists available agents and capabilities
- **`exit`/`quit`** - Graceful conversation termination

#### Visual Enhancements
- **Colored output** for better readability
- **Progress bars** for operations
- **Status messages** with clear feedback
- **Real-time statistics** display

### ✅ Intelligent Handoffs

#### Bidirectional Handoffs
- **Math Agent** → **History Agent** for historical content
- **History Agent** → **Math Agent** for mathematical content
- **Comprehensive keyword detection** for both domains

#### Enhanced Content Detection
- **Historical keywords**: history, war, emperor, ancient, civilization, etc.
- **Mathematical keywords**: calculate, sum, multiply, equation, formula, etc.
- **Automatic routing** based on detected content

#### Direct Responses
- **No redundant questions** when queries are clear
- **Immediate answers** for straightforward questions
- **Comprehensive responses** for complex topics

### ✅ Improved Agent Prompts

#### Enhanced Instructions
- **Comprehensive keyword lists** for content detection
- **Bidirectional handoff capability** between specialists
- **Clear role definitions** for each agent type
- **Graceful fallback** to general agent

#### Better User Experience
- **Natural conversation flow** without interruptions
- **Seamless transitions** between domains
- **Clear, helpful responses** from specialists
- **Context-aware routing** based on question content

### ✅ Robust Input Validation

#### Empty Input Handling
- **Detection of empty input** with warning messages
- **Graceful handling** of invalid commands
- **Clear error feedback** for users

#### Command Recognition
- **Case-insensitive commands** (help, HELP, Help)
- **Multiple exit options** (exit, quit)
- **Special command processing** without affecting conversation flow

### ✅ Performance Improvements

#### Faster Response Times
- **Optimized prompts** reduce unnecessary processing
- **Direct responses** eliminate redundant questions
- **Efficient handoffs** minimize transition time

#### Real-time Statistics
- **Live metrics** show conversation progress
- **Performance tracking** for response times
- **Agent utilization** statistics

## Best Practices

### 1. Agent Design

- **Clear Instructions**: Give agents explicit instructions about when to handoff
- **Avoid Self-Handoffs**: Prevent agents from handing off to themselves
- **Final Responses**: Ensure specialist agents provide final responses, not more handoffs
- **Context Awareness**: Make agents aware of their role and capabilities

**Agent Design Principles:**
- **Specialization**: Each agent should have a clear, focused area of expertise
- **Boundary Definition**: Clearly define what each agent can and cannot handle
- **Handoff Triggers**: Explicitly define when agents should initiate handoffs
- **Response Quality**: Ensure agents provide high-quality, complete responses
- **User Experience**: Design agents to provide smooth, natural conversations

**Example Agent Instructions:**
```
Math Agent: "You are a mathematics specialist. Handle all mathematical questions, 
calculations, and problem-solving. If a question mentions history or other domains, 
handoff to the appropriate specialist. Always provide step-by-step solutions when possible."

History Agent: "You are a history specialist. Provide detailed historical information, 
context, and analysis. Never handoff to other agents - provide complete historical answers."
```

### 2. Permission Configuration

- **Explicit Permissions**: Always configure explicit permissions for each agent
- **Bidirectional Permissions**: Consider if agents need to handoff back to each other
- **Fallback Agents**: Always have a fallback agent for failed handoffs

**Permission Design Strategy:**
- **Principle of Least Privilege**: Only grant permissions that are absolutely necessary
- **Hierarchical Structure**: Design permissions in a logical hierarchy
- **Security Considerations**: Consider sensitive information and access controls
- **Scalability**: Design permissions to accommodate future agent additions
- **Audit Trail**: Ensure all handoffs are logged and traceable

**Example Permission Configuration:**
```php
'permissions' => [
    'general_agent' => ['math_agent', 'history_agent', 'technical_agent'],
    'math_agent' => ['general_agent'], // Can return to general agent
    'history_agent' => ['general_agent'], // Can return to general agent
    'technical_agent' => ['general_agent'], // Can return to general agent
    'admin_agent' => ['*'], // Full access for administrative purposes
],
```

**Permission Best Practices:**
- **Clear Documentation**: Document the purpose of each permission
- **Regular Review**: Periodically review and update permissions
- **Testing**: Test permission configurations thoroughly
- **Monitoring**: Monitor for unauthorized handoff attempts

### 3. Testing

- **Use Debug Mode**: Always test with `--debug` to see the full flow
- **Test Both Modes**: Test both advanced and basic handoff modes
- **Edge Cases**: Test with various question types and scenarios
- **Error Handling**: Test what happens when permissions are missing

**Comprehensive Testing Strategy:**
- **Unit Testing**: Test individual components in isolation
- **Integration Testing**: Test how components work together
- **End-to-End Testing**: Test complete handoff workflows
- **Performance Testing**: Test system performance under load
- **Security Testing**: Test permission and access controls

**Testing Scenarios:**
```
✅ Valid Scenarios:
- Simple handoff (general → specialist)
- Reverse handoff (specialist → general)
- Parallel handoff (multiple specialists)
- Cached responses (performance testing)
- Suggestion-based routing

❌ Edge Cases:
- Invalid agent IDs
- Missing permissions
- Network failures
- Timeout scenarios
- Empty responses
- Infinite handoff loops
```

**Testing Checklist:**
- [ ] Basic handoff functionality
- [ ] Advanced handoff features
- [ ] Permission validation
- [ ] Error handling
- [ ] Performance under load
- [ ] Cache functionality
- [ ] Suggestion accuracy
- [ ] Parallel execution
- [ ] Reversible handoffs
- [ ] Async processing

### 4. Monitoring

- **Logs**: Monitor logs for handoff failures and permission issues
- **Metrics**: Use metrics to track handoff success rates
- **Performance**: Monitor handoff timing and performance
- **Cache Performance**: Monitor cache hit rates for suggestions and parallel results
- **Reversal Patterns**: Track how often users reverse handoffs
- **Parallel Efficiency**: Monitor parallel handoff success rates and response times

**Monitoring Dashboard Metrics:**
- **Handoff Success Rate**: Percentage of successful handoffs
- **Average Response Time**: Time from request to response
- **Cache Hit Rate**: Percentage of requests served from cache
- **Agent Utilization**: Which agents are used most frequently
- **Error Rates**: Frequency of handoff failures
- **User Satisfaction**: Based on follow-up questions or reversals

**Key Performance Indicators (KPIs):**
```
📊 Handoff Metrics:
- Success Rate: >95%
- Average Response Time: <2 seconds
- Cache Hit Rate: >60%
- Error Rate: <1%
- User Satisfaction: >90%

📈 Advanced Features:
- Parallel Handoff Success: >90%
- Suggestion Accuracy: >85%
- Reversal Rate: <10%
- Async Processing Success: >98%
```

**Alerting Strategy:**
- **Critical Alerts**: Handoff failures, permission errors
- **Warning Alerts**: High response times, low cache hit rates
- **Info Alerts**: New patterns, usage statistics
- **Performance Alerts**: System resource usage, API rate limits

**Monitoring Tools:**
- **Application Logs**: Laravel logging for detailed handoff events
- **Metrics Collection**: Custom metrics for handoff performance
- **Health Checks**: Regular system health monitoring
- **User Analytics**: Track user behavior and satisfaction

### 5. Advanced Features Best Practices

#### Reversible Handoffs

- **Limit Reversals**: Set reasonable limits to prevent infinite loops
- **Context Preservation**: Ensure context is properly maintained during reversals
- **User Experience**: Provide clear indicators when reversals are available
- **Performance**: Monitor reversal performance impact

#### Parallel Handoffs

- **Concurrency Limits**: Set appropriate limits based on your system capacity
- **Timeout Configuration**: Configure timeouts to prevent hanging requests
- **Response Merging**: Implement intelligent response merging strategies
- **Error Handling**: Handle partial failures gracefully

#### Intelligent Caching

- **TTL Configuration**: Set appropriate cache TTL based on data freshness requirements
- **Cache Keys**: Use descriptive cache keys for easy debugging
- **Cache Invalidation**: Implement proper cache invalidation strategies
- **Memory Usage**: Monitor cache memory usage

#### Handoff Suggestions

- **Confidence Thresholds**: Set appropriate confidence thresholds for suggestions
- **Context Analysis**: Ensure suggestions consider full conversation context
- **Fallback Strategies**: Have fallback strategies for low-confidence suggestions
- **User Control**: Allow users to override suggestions when needed

#### Asynchronous Processing

- **Queue Configuration**: Configure appropriate queue settings
- **Error Handling**: Implement robust error handling for async operations
- **Monitoring**: Monitor queue performance and job failures
- **User Feedback**: Provide appropriate feedback for async operations

## Troubleshooting

### Common Issues

1. **Permission Denied**: Check that agents have proper permissions configured
2. **Agent Not Found**: Ensure agents are registered with correct IDs
3. **Infinite Handoffs**: Check that agents don't handoff to themselves
4. **Empty Responses**: Verify that final agents provide actual responses
5. **Cache Misses**: Check cache configuration and TTL settings
6. **Slow Performance**: Monitor API response times and cache hit rates
7. **Suggestion Failures**: Verify context analyzer configuration
8. **Parallel Timeouts**: Check timeout settings for parallel handoffs

### Debug Steps

1. Run with `--debug` to see detailed flow
2. Check logs for specific error messages
3. Verify agent IDs and permissions
4. Test with `--no-advanced` to isolate issues
5. Check cache configuration and hit rates
6. Monitor API rate limits and quotas
7. Verify environment variable configuration
8. Test individual components in isolation

### Diagnostic Commands

```bash
# Basic diagnostics
php artisan agent:handoff-test --debug

# Test specific features
php artisan agent:handoff-test --parallel --cache-debug
php artisan agent:handoff-test --reverse --debug
php artisan agent:handoff-test --suggestion-debug

# Performance testing
php artisan agent:handoff-test --max-turns=10 --debug

# Cache testing
php artisan agent:handoff-test --parallel --cache-debug
```

### Common Error Messages and Solutions

**"Permission denied for handoff"**
- **Cause**: Agent doesn't have permission to handoff to target agent
- **Solution**: Check permissions configuration in `config/agents.php`

**"Agent not found: [agent_id]"**
- **Cause**: Agent is not registered in the system
- **Solution**: Ensure agent is properly registered with correct ID

**"Cache key generation failed"**
- **Cause**: Invalid cache key format or configuration
- **Solution**: Check cache prefix and TTL configuration

**"Parallel handoff timeout"**
- **Cause**: Parallel handoffs taking too long
- **Solution**: Increase timeout setting or reduce concurrent agents

**"Suggestion confidence too low"**
- **Cause**: Context analyzer cannot determine appropriate agent
- **Solution**: Adjust confidence threshold or improve agent capabilities

### Performance Optimization

**Slow Response Times:**
- Enable caching for frequently asked questions
- Optimize agent instructions for faster responses
- Consider parallel handoffs for complex queries
- Monitor API rate limits and quotas

**High API Usage:**
- Implement aggressive caching strategies
- Use suggestion-based routing to reduce unnecessary handoffs
- Optimize agent capabilities to reduce handoff frequency
- Consider async processing for non-critical handoffs

**Cache Performance:**
- Monitor cache hit rates and adjust TTL settings
- Use appropriate cache prefixes for easy debugging
- Implement cache warming for common scenarios
- Consider distributed caching for high-traffic applications

## Extending the System

You can extend the advanced handoff system by:

1. **Custom State Providers**: Implement the `ConversationStateManager` interface
2. **Custom Metrics**: Add custom metric processors to the `MetricsCollector`
3. **Enhanced Security**: Extend the `SecurityManager` with additional controls
4. **Custom Selection Logic**: Implement custom agent selection in the `HandoffOrchestrator`
5. **Custom Test Commands**: Create specialized test commands for your use cases
6. **Custom Cache Providers**: Implement custom caching strategies for the `IntelligentCacheManager`
7. **Enhanced Context Analysis**: Extend the `ContextAnalyzer` with domain-specific logic
8. **Custom Parallel Strategies**: Implement custom parallel execution strategies
9. **Advanced Reversal Logic**: Create custom reversal strategies for complex workflows
10. **Custom Suggestion Algorithms**: Implement domain-specific suggestion algorithms

## Example: Customer Service Handoff

Here's a complete example for a customer service scenario:

```php
// Customer service agent
$customerServiceAgent = $manager->agent(null, 
    "You are a Customer Service Agent. Route customers to specialists: " .
    "For technical issues: [[handoff:technical_support]] " .
    "For billing issues: [[handoff:billing_agent]] " .
    "For sales inquiries: [[handoff:sales_agent]]"
);

// Technical support agent
$technicalSupportAgent = $manager->agent(null, 
    "You are a Technical Support Specialist. Provide technical assistance. " .
    "NEVER use handoff syntax. Always provide direct technical help."
);

// Billing agent
$billingAgent = $manager->agent(null, 
    "You are a Billing Specialist. Handle billing and payment issues. " .
    "NEVER use handoff syntax. Always provide direct billing help."
);

// Sales agent
$salesAgent = $manager->agent(null, 
    "You are a Sales Specialist. Handle product inquiries and sales. " .
    "NEVER use handoff syntax. Always provide direct sales assistance."
);

// Configure permissions
$securityConfig = [
    'handoff' => [
        'permissions' => [
            'customer_service' => ['technical_support', 'billing_agent', 'sales_agent'],
            'technical_support' => ['customer_service'],
            'billing_agent' => ['customer_service'],
            'sales_agent' => ['customer_service'],
        ]
    ]
];
```

This setup provides a complete customer service handoff system with proper routing and specialist agents.
