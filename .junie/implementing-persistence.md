# OpenAI Agents Persistence Layer - Implementation Specification v2

## 🎯 Updated Design Principles
- **Extend, Don't Replace**: Build on existing `Agent` and `AgentManager` classes
- **Leverage Existing Infrastructure**: Use current event system, options, and patterns
- **Backward Compatible**: All existing code must continue working unchanged
- **Progressive Enhancement**: Persistence is opt-in via config or method calls
- **Respect Architecture**: Work with Responses API, Chat Completions API, and existing tools

---

## 📦 Updated Package Structure

```
src/
├── Persistence/
│   ├── Contracts/
│   │   └── ConversationStore.php
│   ├── Stores/
│   │   ├── DatabaseStore.php
│   │   ├── CacheStore.php
│   │   └── NullStore.php
│   ├── Models/
│   │   ├── Conversation.php
│   │   └── Message.php
│   ├── Strategies/
│   │   ├── ContextStrategy.php
│   │   ├── RecentMessagesStrategy.php
│   │   └── SummaryStrategy.php
│   └── PersistentAgentTrait.php  # Trait to add persistence to Agent
├── AgentManager.php              # Extend existing
├── Agent.php                     # Extend existing
└── PersistenceServiceProvider.php

database/
└── migrations/
    └── 2024_01_01_000000_create_agent_conversations_tables.php

config/
└── agent-persistence.php
```

---

## 🏗️ Phase 1: Core Implementation (Ship in 1 week)

### 1.1 PersistentAgentTrait - Add persistence capabilities to Agent

```php
<?php

namespace Sapiensly\OpenaiAgents\Persistence;

use Sapiensly\OpenaiAgents\Persistence\Contracts\ConversationStore;
use Sapiensly\OpenaiAgents\Persistence\Strategies\ContextStrategy;
use Sapiensly\OpenaiAgents\Events\AgentResponseGenerated;
use Illuminate\Support\Str;

trait PersistentAgentTrait
{
    protected ?string $conversationId = null;
    protected ?ConversationStore $persistenceStore = null;
    protected ?ContextStrategy $contextStrategy = null;
    protected bool $persistenceEnabled = false;
    protected bool $autoSummarize = true;
    protected int $summarizeAfter = 20;
    
    /**
     * Enable persistence for this agent with a conversation ID
     */
    public function withConversation(string $conversationId = null): self
    {
        $this->conversationId = $conversationId ?: (string) Str::uuid();
        $this->persistenceEnabled = true;
        
        if (!$this->persistenceStore) {
            $this->persistenceStore = app(ConversationStore::class);
        }
        
        if (!$this->contextStrategy) {
            $this->contextStrategy = app(ContextStrategy::class);
        }
        
        // Find or create conversation
        $this->persistenceStore->findOrCreate($this->conversationId, [
            'agent_id' => $this->id,
            'model' => $this->options->get('model'),
        ]);
        
        // Load existing context
        $this->loadPersistedContext();
        
        return $this;
    }
    
    /**
     * Get the current conversation ID
     */
    public function getConversationId(): ?string
    {
        return $this->conversationId;
    }
    
    /**
     * Load persisted context into the agent's messages
     */
    protected function loadPersistedContext(): void
    {
        if (!$this->persistenceEnabled || !$this->conversationId) {
            return;
        }
        
        $messages = $this->persistenceStore->getRecentMessages($this->conversationId);
        $summary = $this->persistenceStore->getSummary($this->conversationId);
        
        $context = $this->contextStrategy->buildContext($messages, $summary);
        
        if ($context) {
            // Prepend context as system message if not empty
            $this->updateConversationSystemPrompt($context);
        }
        
        // Load recent messages into memory (last N turns)
        $maxTurns = $this->options->get('max_turns') ?? 10;
        $recentMessages = array_slice($messages, -($maxTurns * 2));
        
        foreach ($recentMessages as $msg) {
            if (!$this->messageExists($msg)) {
                $this->messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
        }
    }
    
    /**
     * Check if a message already exists in memory
     */
    protected function messageExists(array $message): bool
    {
        foreach ($this->messages as $existing) {
            if ($existing['role'] === $message['role'] && 
                $existing['content'] === $message['content']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Persist a message exchange
     */
    protected function persistMessage(string $role, string $content, array $metadata = []): void
    {
        if (!$this->persistenceEnabled || !$this->conversationId) {
            return;
        }
        
        $this->persistenceStore->addMessage($this->conversationId, [
            'role' => $role,
            'content' => $content,
            'token_count' => $metadata['token_count'] ?? null,
            'metadata' => $metadata,
        ]);
        
        // Check if we should summarize
        $this->maybeSummarize();
    }
    
    /**
     * Check and trigger summarization if needed
     */
    protected function maybeSummarize(): void
    {
        if (!$this->autoSummarize || !$this->persistenceEnabled) {
            return;
        }
        
        $messages = $this->persistenceStore->getRecentMessages($this->conversationId, 100);
        
        if (count($messages) >= $this->summarizeAfter && count($messages) % 10 === 0) {
            dispatch(function () use ($messages) {
                $this->generateAndStoreSummary($messages);
            })->afterResponse();
        }
    }
    
    /**
     * Generate and store a summary
     */
    protected function generateAndStoreSummary(array $messages): void
    {
        // Use a separate agent instance for summarization
        $summaryAgent = app(\Sapiensly\OpenaiAgents\AgentManager::class)->agent([
            'model' => config('agent-persistence.summarization.model', 'gpt-3.5-turbo'),
            'temperature' => 0.3,
            'max_tokens' => 500,
        ]);
        
        $transcript = collect($messages)
            ->map(fn($m) => strtoupper($m['role']) . ': ' . $m['content'])
            ->implode("\n\n");
        
        $summary = $summaryAgent->chat(
            "Summarize this conversation concisely, keeping key facts and context:\n\n" . 
            substr($transcript, 0, 8000) // Limit input size
        );
        
        $this->persistenceStore->updateSummary($this->conversationId, $summary);
    }
    
    /**
     * Clear the conversation and start fresh
     */
    public function clearConversation(): self
    {
        if ($this->conversationId && $this->persistenceStore) {
            $this->persistenceStore->delete($this->conversationId);
        }
        
        $this->conversationId = null;
        $this->messages = [];
        $this->persistenceEnabled = false;
        
        return $this;
    }
    
    /**
     * Get persisted conversation history
     */
    public function getPersistedHistory(int $limit = 50): array
    {
        if (!$this->persistenceEnabled || !$this->conversationId) {
            return [];
        }
        
        return $this->persistenceStore->getRecentMessages($this->conversationId, $limit);
    }
}
```

### 1.2 Extend Agent Class to Include Persistence

```php
<?php
// In src/Agent.php - Add at the top of the class

namespace Sapiensly\OpenaiAgents;

use Sapiensly\OpenaiAgents\Persistence\PersistentAgentTrait;

class Agent
{
    use FunctionSchemaGenerator;
    use PersistentAgentTrait; // Add this trait
    
    // ... existing code ...
    
    /**
     * Override chat method to add persistence hooks
     */
    public function chat(string $message, array|null $toolDefinitions = null, mixed $outputType = null): string
    {
        // Store user message if persistence is enabled
        if ($this->persistenceEnabled) {
            $this->persistMessage('user', $message);
        }
        
        // Add to in-memory messages as before
        $this->messages[] = ['role' => 'user', 'content' => $message];
        
        // Call the existing chat logic
        $response = $this->chatWithResponsesAPI($message, $toolDefinitions, $outputType);
        
        // Store assistant response if persistence is enabled
        if ($this->persistenceEnabled) {
            $this->persistMessage('assistant', $response, [
                'token_count' => $this->getTokenUsage(),
                'model' => $this->options->get('model'),
            ]);
        }
        
        return $response;
    }
    
    /**
     * Override chatStreamed to add persistence hooks
     */
    public function chatStreamed(string $message, array|null $toolDefinitions = null, mixed $outputType = null): iterable
    {
        // Store user message if persistence is enabled
        if ($this->persistenceEnabled) {
            $this->persistMessage('user', $message);
        }
        
        $fullResponse = '';
        
        // Stream the response
        foreach ($this->chatStreamedWithChatAPI($message, $toolDefinitions, $outputType) as $chunk) {
            $fullResponse .= $chunk;
            yield $chunk;
        }
        
        // Store complete assistant response if persistence is enabled
        if ($this->persistenceEnabled && !empty($fullResponse)) {
            $this->persistMessage('assistant', $fullResponse, [
                'token_count' => $this->getTokenUsage(),
                'model' => $this->options->get('model'),
            ]);
        }
    }
}
```

### 1.3 Extend AgentManager to Support Persistence

```php
<?php
// In src/AgentManager.php - Add new methods

namespace Sapiensly\OpenaiAgents;

class AgentManager
{
    // ... existing code ...
    
    /**
     * Create a persistent agent with conversation support
     */
    public function persistent(string $conversationId = null, AgentOptions|array|null $options = null): Agent
    {
        $agent = $this->agent($options);
        $agent->withConversation($conversationId);
        return $agent;
    }
    
    /**
     * Continue an existing conversation
     */
    public function continueConversation(string $conversationId, AgentOptions|array|null $options = null): Agent
    {
        if (!$conversationId) {
            throw new \InvalidArgumentException('Conversation ID is required to continue a conversation');
        }
        
        return $this->persistent($conversationId, $options);
    }
    
    /**
     * Create a new conversation
     */
    public function newConversation(AgentOptions|array|null $options = null): Agent
    {
        return $this->persistent(null, $options);
    }
}
```

### 1.4 Update Facades for Better DX

```php
<?php

namespace Sapiensly\OpenaiAgents\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Sapiensly\OpenaiAgents\Agent agent(AgentOptions|array|null $options = null, string|null $systemPrompt = null)
 * @method static \Sapiensly\OpenaiAgents\Agent persistent(string $conversationId = null, AgentOptions|array|null $options = null)
 * @method static \Sapiensly\OpenaiAgents\Agent continueConversation(string $conversationId, AgentOptions|array|null $options = null)
 * @method static \Sapiensly\OpenaiAgents\Agent newConversation(AgentOptions|array|null $options = null)
 * @method static \Sapiensly\OpenaiAgents\Runner runner(Agent|null $agent = null, string|null $name = null, int|null $maxTurns = null)
 */
class Agent extends Facade
{
    protected static function getFacadeAccessor()
    {
        return AgentManager::class;
    }
}
```

### 1.5 Database Store Implementation

```php
<?php

namespace Sapiensly\OpenaiAgents\Persistence\Stores;

use Sapiensly\OpenaiAgents\Persistence\Contracts\ConversationStore;
use Sapiensly\OpenaiAgents\Persistence\Models\Conversation;
use Sapiensly\OpenaiAgents\Persistence\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DatabaseStore implements ConversationStore
{
    protected string $cachePrefix = 'agent:conv:';
    protected int $cacheTtl = 3600;
    
    public function findOrCreate(string $id, ?array $metadata = []): array
    {
        $conversation = Conversation::firstOrCreate(
            ['id' => $id],
            [
                'agent_id' => $metadata['agent_id'] ?? null,
                'user_id' => auth()->id(),
                'metadata' => $metadata,
            ]
        );
        
        return $conversation->toArray();
    }
    
    public function addMessage(string $conversationId, array $message): void
    {
        DB::transaction(function () use ($conversationId, $message) {
            Message::create([
                'conversation_id' => $conversationId,
                'role' => $message['role'],
                'content' => $message['content'],
                'token_count' => $message['token_count'] ?? null,
                'metadata' => $message['metadata'] ?? null,
            ]);
            
            Conversation::where('id', $conversationId)->update([
                'last_message_at' => now(),
                'message_count' => DB::raw('message_count + 1'),
                'total_tokens' => DB::raw('total_tokens + ' . ($message['token_count'] ?? 0)),
            ]);
        });
        
        // Clear cache
        Cache::tags(['conversation:' . $conversationId])->flush();
    }
    
    public function getRecentMessages(string $conversationId, int $limit = 20): array
    {
        $cacheKey = $this->cachePrefix . $conversationId . ':messages:' . $limit;
        
        return Cache::tags(['conversation:' . $conversationId])
            ->remember($cacheKey, $this->cacheTtl, function () use ($conversationId, $limit) {
                return Message::where('conversation_id', $conversationId)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get()
                    ->reverse()
                    ->values()
                    ->toArray();
            });
    }
    
    public function getSummary(string $conversationId): ?string
    {
        return Cache::tags(['conversation:' . $conversationId])
            ->remember($this->cachePrefix . $conversationId . ':summary', $this->cacheTtl, function () use ($conversationId) {
                return Conversation::find($conversationId)?->summary;
            });
    }
    
    public function updateSummary(string $conversationId, string $summary): void
    {
        Conversation::where('id', $conversationId)->update([
            'summary' => $summary,
            'summary_updated_at' => now(),
        ]);
        
        Cache::tags(['conversation:' . $conversationId])->flush();
    }
    
    public function delete(string $conversationId): void
    {
        DB::transaction(function () use ($conversationId) {
            Message::where('conversation_id', $conversationId)->delete();
            Conversation::where('id', $conversationId)->delete();
        });
        
        Cache::tags(['conversation:' . $conversationId])->flush();
    }
}
```

### 1.6 Configuration File

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Persistence
    |--------------------------------------------------------------------------
    */
    'enabled' => env('AGENT_PERSISTENCE_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Default Store
    |--------------------------------------------------------------------------
    | Supported: "database", "cache", "null"
    */
    'default' => env('AGENT_PERSISTENCE_STORE', 'database'),
    
    /*
    |--------------------------------------------------------------------------
    | Store Configurations
    |--------------------------------------------------------------------------
    */
    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
                'tags' => true, // Use cache tags for better invalidation
            ],
        ],
        
        'cache' => [
            'driver' => 'cache',
            'store' => env('CACHE_DRIVER', 'redis'),
            'ttl' => 86400, // 24 hours
            'prefix' => 'agent_conv:',
        ],
        
        'null' => [
            'driver' => 'null', // No persistence (for testing)
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Context Building Strategy
    |--------------------------------------------------------------------------
    */
    'context' => [
        'strategy' => \Sapiensly\OpenaiAgents\Persistence\Strategies\RecentMessagesStrategy::class,
        'max_messages' => 20,
        'max_tokens' => 3000,
        'include_summary' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Summarization Settings
    |--------------------------------------------------------------------------
    */
    'summarization' => [
        'enabled' => true,
        'after_messages' => 20,
        'model' => env('AGENT_SUMMARY_MODEL', 'gpt-3.5-turbo'),
        'max_length' => 500,
        'temperature' => 0.3,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Auto Cleanup
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'enabled' => false,
        'older_than_days' => 90,
        'keep_summaries' => true,
    ],
];
```

### 1.7 Migration File

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('summary_updated_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            // Composite indexes for common queries
            $table->index(['user_id', 'last_message_at']);
            $table->index(['agent_id', 'created_at']);
        });
        
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')
                ->constrained('agent_conversations')
                ->cascadeOnDelete();
            $table->enum('role', ['system', 'user', 'assistant', 'developer', 'tool']);
            $table->text('content');
            $table->unsignedInteger('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            
            // Optimized for fetching conversation history
            $table->index(['conversation_id', 'created_at']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('agent_conversations');
    }
};
```

---

## 📖 Usage Examples

### Basic Persistent Chat

```php
use Sapiensly\OpenaiAgents\Facades\Agent;

// Start a new persistent conversation
$agent = Agent::newConversation();
$response = $agent->chat("Hello! I'm working on a Laravel project");
$conversationId = $agent->getConversationId();

// Continue the same conversation later
$agent = Agent::continueConversation($conversationId);
$response = $agent->chat("What did I just tell you about?");
// Response: "You mentioned you're working on a Laravel project..."
```

### Using with Existing Agent Features

```php
// Persistence works with all existing features
$agent = Agent::persistent()
    ->useRAG($vectorStoreId)
    ->useWebSearch()
    ->useFunctions(WeatherService::class)
    ->chat("What's the weather like in the docs?");

// Works with streaming
foreach ($agent->chatStreamed("Tell me more") as $chunk) {
    echo $chunk;
}
```

### In a Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sapiensly\OpenaiAgents\Facades\Agent;

class ChatController extends Controller
{
    public function chat(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'nullable|uuid',
            'message' => 'required|string|max:1000',
        ]);
        
        // Continue existing or start new conversation
        $agent = $validated['conversation_id'] 
            ? Agent::continueConversation($validated['conversation_id'])
            : Agent::newConversation();
        
        // All existing agent features work
        if ($request->has('use_web_search')) {
            $agent->useWebSearch();
        }
        
        $response = $agent->chat($validated['message']);
        
        return response()->json([
            'conversation_id' => $agent->getConversationId(),
            'response' => $response,
            'token_usage' => $agent->getTokenUsage(),
        ]);
    }
    
    public function history($conversationId)
    {
        $agent = Agent::continueConversation($conversationId);
        
        return response()->json([
            'history' => $agent->getPersistedHistory(),
            'summary' => $agent->persistenceStore?->getSummary($conversationId),
        ]);
    }
}
```

### With Multi-Agent Runner

```php
// Persistence works with Runner and handoffs
$runner = Agent::runner();

$japanAgent = Agent::persistent('japan-conv-123')
    ->setInstructions("You are an expert on Japan");

$mathAgent = Agent::persistent('math-conv-456')
    ->setInstructions("You are a math expert");

$runner->registerAgent('japan_agent', $japanAgent, 'Questions about Japan');
$runner->registerAgent('math_agent', $mathAgent, 'Math questions');

// Each agent maintains its own persistent conversation
$response = $runner->run("Tell me about Tokyo");
```

### Event Integration

```php
// The existing event system works with persistence
use Sapiensly\OpenaiAgents\Events\AgentResponseGenerated;

Event::listen(AgentResponseGenerated::class, function ($event) {
    // The event now includes conversation_id in metadata
    Log::info('Response generated', [
        'conversation_id' => $event->metadata['conversation_id'] ?? null,
        'agent_id' => $event->agentId,
        'tokens' => $event->metadata['estimated_total_token_usage'],
    ]);
});
```

---

## 🔄 Migration Path

### For Existing Users (Zero Breaking Changes)

```php
// Existing code continues to work exactly as before
$agent = Agent::agent();
$response = $agent->chat("Hello"); // Works, no persistence

// Opt-in to persistence when ready
$agent = Agent::agent()->withConversation();
$response = $agent->chat("Hello"); // Now with persistence!

// Or use the convenience methods
$agent = Agent::persistent(); // New persistent agent
```

### Progressive Adoption

```php
// Step 1: Keep using existing code
$agent = Agent::agent();

// Step 2: Add persistence to specific agents
if ($needsPersistence) {
    $agent->withConversation($conversationId);
}

// Step 3: Use new convenience methods
$agent = Agent::newConversation();
```

---

## 🚀 Implementation Steps

### Week 1: Core
1. **Day 1-2**: Implement trait, extend Agent/AgentManager
2. **Day 3**: DatabaseStore and migrations
3. **Day 4**: Context strategies and summarization
4. **Day 5**: Testing with existing features
5. **Day 6-7**: Documentation and examples

### Week 2: Polish
1. Cache optimization with tags
2. Add Redis store
3. Artisan commands for cleanup
4. Performance testing
5. Edge cases and error handling

### Week 3: Advanced
1. Multi-tenant support
2. Encryption for sensitive conversations
3. Export/import functionality
4. Analytics and reporting
5. Advanced summarization strategies

---

## ⚠️ Important Considerations

### 1. **Token Tracking**
The existing code already tracks tokens via `$this->totalTokens`. We should persist this:
```php
'total_tokens' => $agent->getTokenUsage()
```

### 2. **Working with Tools**
Persistence must handle tool calls properly:
```php
// In the trait
if ($message['role'] === 'tool' || $message['role'] === 'developer') {
    // Store tool results differently
    $this->persistMessage($message['role'], $message['content'], [
        'tool_call_id' => $message['tool_call_id'] ?? null,
        'name' => $message['name'] ?? null,
    ]);
}
```

### 3. **Responses API vs Chat API**
Both APIs are used in the codebase. Ensure persistence works with both:
```php
// The trait hooks into the main chat() method
// which delegates to either API, so both are covered
```

### 4. **Event System Integration**
The existing `AgentResponseGenerated` event should include conversation info:
```php
// In Agent::fireResponseEvent()
$metadata = [
    // ... existing metadata ...
    'conversation_id' => $this->conversationId,
    'persistence_enabled' => $this->persistenceEnabled,
];
```

### 5. **Runner Compatibility**
Ensure persistence works with multi-agent handoffs:
```php
// Each agent in a Runner can have its own conversation
$runner->registerAgent('support', 
    Agent::persistent('support-conv-123'),
    'Customer support questions'
);
```

---

## 🎯 Key Success Metrics

1. **Zero Breaking Changes**: All existing tests pass
2. **Performance**: < 50ms overhead for persistence operations
3. **Scalability**: Handle 1M+ conversations efficiently
4. **DX**: Intuitive API that feels natural to Laravel developers
5. **Compatibility**: Works with all existing features (RAG, tools, streaming, etc.)

---

## 📝 Testing Checklist

- [ ] Existing code works unchanged
- [ ] Persistence with basic chat
- [ ] Persistence with streaming
- [ ] Persistence with RAG
- [ ] Persistence with function tools
- [ ] Persistence with web search
- [ ] Persistence with MCP servers
- [ ] Persistence with Runner/handoffs
- [ ] Context loading and summarization
- [ ] Cache invalidation
- [ ] Concurrent conversations
- [ ] Large conversation handling (100+ messages)

---

**This spec is designed to integrate seamlessly with your existing sophisticated codebase while adding powerful persistence capabilities. Start with the trait implementation and gradually add features without breaking anything!** 🚀
