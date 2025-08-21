### Overview
This document explains, in depth, how persistence works for:
- Agent definitions (what an agent is: its id, options, instructions, etc.).
- Conversation history (messages exchanged during a chat).

By default, agents are stateless and keep their message history only in memory within the PHP process. No database or cache is used unless you explicitly opt in.

### Two Types of Persistence
- Agent Definition Persistence
  - Controls whether agent definitions are stored and retrievable (independent of chat history).
  - Configured in the `definitions` section of the unified `sapiensly-openai-agents.php` config file.
  - Does not automatically make conversations persistent.

- Conversation Persistence
  - Stores and restores chat histories across requests.
  - Configured in the `persistence` section of the unified `sapiensly-openai-agents.php` config file.
  - Requires two things:
    1) Global feature enabled in configuration.
    2) Per-agent opt-in via withConversation().

### Quick Start: Enabling Conversation Persistence
1) Ensure global persistence is enabled (default is enabled):
   ```php
   // In sapiensly-openai-agents.php config:
   'persistence' => [
       'enabled' => env('AGENT_PERSISTENCE_ENABLED', true),
       'default' => env('AGENT_PERSISTENCE_STORE', 'database'),
       // ...
   ]
   ```

2) Opt in at agent creation time with withConversation():

```php
php use Sapiensly\OpenaiAgents\AgentOptions; 
use Sapiensly\OpenaiAgents\Facades\Agent;
$options = (new AgentOptions())->setTemperature(0.4) ->setInstructions('Always answer in Spanish.');
$agent = Agent::agent(options)->withConversation(); // creates a new conversation id
$response = $agent->chat('Hello, my name is John');
// Retrieve the conversation and agent ids to resume later:
$conversationId = $agent->getConversationId(); 
$agentId = $agent->getId();
// Later (e.g., another request):
$rebuilt = Agent::load(agentId)->withConversation(conversationId);
$response2 = $rebuilt->chat('What did I say previously?');
```
Notes:
- withConversation() without arguments will create a new UUID as the conversation id.
- Passing an existing id will resume that conversation.

### Agent Definition Persistence Configuration

In the `sapiensly-openai-agents.php` config file:
```php
'definitions' => [ 
    'enabled' => env('AGENT_DEFINITIONS_ENABLED', true), 
    'store' => env('AGENT_DEFINITIONS_STORE', 'database'),
    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'sqlite'),
            'table' => 'agent_definitions',
            'cache' => [
                'enabled' => env('AGENT_DEFINITIONS_CACHE_ENABLED', false),
                'ttl' => (int) env('AGENT_DEFINITIONS_CACHE_TTL', 86400),
                'tags' => env('AGENT_DEFINITIONS_CACHE_TAGS', true),
            ],
        ],
        
        'cache' => [
            'driver' => 'cache',
            'store' => env('CACHE_DRIVER', 'redis'),
            'prefix' => env('AGENT_DEFINITIONS_CACHE_PREFIX', 'agent_def:'),
            'ttl' => (int) env('AGENT_DEFINITIONS_CACHE_TTL', 86400),
        ],
        
        'null' => [
            'driver' => 'null',
        ],
    ],
],
```
This subsystem is independent: enabling definition persistence does not automatically persist conversations.

### Conversation Persistence Configuration

In the `sapiensly-openai-agents.php` config file:

```php
'persistence' => [ 
    'enabled' => env('AGENT_PERSISTENCE_ENABLED', true), 
    'default' => env('AGENT_PERSISTENCE_STORE', 'database'),
    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION'),
            'cache' => [
                'enabled' => env('AGENT_PERSISTENCE_CACHE_ENABLED', false),
                'ttl' => (int) env('AGENT_PERSISTENCE_CACHE_TTL', 3600),
                'tags' => env('AGENT_PERSISTENCE_CACHE_TAGS', true),
            ],
        ],
        
        'cache' => [
            'driver' => 'cache',
            'store' => env('CACHE_DRIVER', 'redis'),
            'ttl' => (int) env('AGENT_PERSISTENCE_CACHE_TTL', 86400),
            'prefix' => env('AGENT_PERSISTENCE_CACHE_PREFIX', 'agent_conv:'),
        ],
        
        'null' => [
            'driver' => 'null',
        ],
    ],
    
    'context' => [
        'strategy' => \Sapiensly\OpenaiAgents\Persistence\Strategies\RecentMessagesStrategy::class,
        'max_messages' => (int) env('AGENT_CONTEXT_MAX_MESSAGES', 20),
        'max_tokens' => (int) env('AGENT_CONTEXT_MAX_TOKENS', 3000),
        'include_summary' => env('AGENT_CONTEXT_INCLUDE_SUMMARY', true),
    ],
    
    'summarization' => [
        'enabled' => env('AGENT_SUMMARIZATION_ENABLED', true),
        'after_messages' => (int) env('AGENT_SUMMARIZATION_AFTER_MESSAGES', 20),
        'model' => env('AGENT_SUMMARY_MODEL', 'gpt-3.5-turbo'),
        'max_length' => (int) env('AGENT_SUMMARY_MAX_LENGTH', 500),
        'temperature' => (float) env('AGENT_SUMMARY_TEMPERATURE', 0.3),
    ],
    
    'cleanup' => [
        'enabled' => env('AGENT_CLEANUP_ENABLED', false),
        'older_than_days' => (int) env('AGENT_CLEANUP_OLDER_THAN_DAYS', 90),
        'keep_summaries' => env('AGENT_CLEANUP_KEEP_SUMMARIES', true),
    ],
],
```

### Lifecycle Inside the Agent (PersistentAgentTrait)
The trait Sapiensly\OpenaiAgents\Persistence\PersistentAgentTrait implements the behavior an Agent uses to persist and hydrate a conversation.

Key internal fields:
- conversationId: string|null
- persistenceStore: ConversationStore|null (resolved via the container)
- contextStrategy: ContextStrategy|null (resolved via the container)
- persistenceEnabled: bool (mirrors global config at the moment of withConversation())
- autoSummarize: bool (defaults true)
- summarizeAfter: int (defaults 20)
- historyHydrated: bool (guards hydration to happen once)

#### withConversation(?string $conversationId = null)
- Resets persistence-related state to avoid stale references (critical when using Agent::load()).
- Sets conversationId to provided value or a newly generated UUID.
- Loads persistenceEnabled from config('sapiensly-openai-agents.persistence.enabled', true).
- Resolves a fresh ConversationStore and ContextStrategy from the container.
- Creates or finds the conversation record by calling persistenceStore->findOrCreate(conversationId, [agent_id, model]).
- Immediately calls hydratePersistedHistoryIfNeeded() to populate message history.

#### Hydration: hydratePersistedHistoryIfNeeded()
- Skips if already hydrated, persistence is disabled, or no conversation id is present.
- Determines a fetch limit from options->max_turns (or 50 as default).
- Fetches persisted messages via persistenceStore->getRecentMessages(conversationId, limit).
- Preserves any in-memory system and developer messages (instructions, system prompts) from the current agent instance.
- Rebuilds the message list as:
  1) preserved system/developer messages
  2) persisted messages sorted by created_at ascending
- Marks as hydrated to avoid double hydration.
- Extensively logs actions to aid debugging.

Why preserve system/developer messages?
- So that core instructions or developer/system prompts configured in-memory are not lost when merging with stored history. The conversation is reconstructed as: instructions + stored turns.

#### Persisting messages: persistMessage($role, $content, array $metadata = [])
- No-ops if persistence is disabled or there is no conversation id.
- Otherwise, calls persistenceStore->addMessage(conversationId, [role, content, token_count, metadata]).
- token_count is optional and can be supplied through metadata.
- Wraps the write in try/catch with logging and rethrows on errors.
- Calls maybeSummarize() at the end as a hook for deferred summarization.

#### Getting stored history: getPersistedHistory(int $limit = 50)
- Returns an array from persistenceStore->getRecentMessages(conversationId, limit), or [] if disabled/no id.

#### Summarization hook: maybeSummarize()
- Controlled by autoSummarize and the global summarization config.
- Retrieves the last N messages and, when thresholds are met (after_messages and on multiples of 10), provides a minimal hook (no model call in-place) intended to be implemented via alternative bindings or background jobs.

#### Clearing and resetting: clearConversation()
- If a conversation exists, calls persistenceStore->delete(conversationId) to wipe persisted data.
- Resets conversationId, disables persistence, and clears in-memory messages.

#### Checking state: isPersistenceEnabled()
- Returns true only if persistenceEnabled is true AND conversationId is present AND a persistence store is set.

### How the Store Is Chosen and Used
- The ConversationStore implementation is resolved from the IoC container. The binding is expected to honor the unified configuration's persistence.default store and its configured driver.
- database store: persists conversations and messages to a DB connection. Can layer a Redis cache for lookups.
- cache store: keeps conversations ephemeral in a cache backend (e.g., Redis) with ttl and prefix.
- null store: performs no persistence (useful for testing or explicit off-switch).

The trait calls these ConversationStore methods (conceptually):
- findOrCreate($conversationId, [agent_id, model])
- getRecentMessages($conversationId, $limit)
- addMessage($conversationId, [role, content, token_count, metadata])
- delete($conversationId)

### Context Strategy and Prompt Construction
- The configured context.strategy (RecentMessagesStrategy by default) represents how the persisted history is selected and injected into a prompt window.
- Related limits (max_messages, max_tokens) and include_summary drive how much of the history is brought into context for a request.
- The trait itself handles hydration (repopulating the agent's internal $messages), while the ContextStrategy governs selection for model calls.

### Configuration Reference (at a glance)
- Unified configuration in `sapiensly-openai-agents.php`:
  - persistence:
    - enabled: true|false (AGENT_PERSISTENCE_ENABLED)
    - default: database|cache|null (AGENT_PERSISTENCE_STORE)
    - stores.database: driver, connection, cache(enabled|ttl|tags)
    - stores.cache: driver, store, ttl, prefix
    - stores.null: driver
    - context: strategy, max_messages, max_tokens, include_summary
    - summarization: enabled, after_messages, model, max_length, temperature
    - cleanup: enabled, older_than_days, keep_summaries

  - definitions:
    - enabled: true|false (AGENT_DEFINITIONS_ENABLED)
    - store: null|database|cache (AGENT_DEFINITIONS_STORE)
    - stores.database: driver, connection, table, cache(enabled|ttl|tags)
    - stores.cache: driver, store, prefix, ttl
    - stores.null: driver

### Usage Patterns and Examples
- Stateless default:
```php
$agent = Agent::agent();
$agent->chat('Hi'); // history is in memory only; new request loses it
```

- Persisted conversation across requests:
```php
$agent = Agent::agent()->withConversation();
$agent->chat('Hi');
$conversationId = $agent->getConversationId();
$agentId = $agent->getId();

// later request
$agent2 = Agent::load($agentId)->withConversation($conversationId);
$agent2->chat('What did I just say?');
```

- Limiting how much history is retained in-memory when hydrating:
  - options->max_turns affects the number of messages retrieved on hydration (default 50 if not set).
  - The context strategy settings further shape what is sent to the model.

### Operational Notes and Best Practices
- Always call withConversation() for any agent whose chat you want to resume later. Without this call, nothing is persisted even if global persistence is enabled.
- Preserve instructions by design: system/developer messages present in the agent at the time of hydration are kept and prepended to the hydrated history. This ensures your instruction stack remains intact.
- Stores and environment:
  - For production, prefer 'database' with optional Redis caching for faster lookups.
  - For ephemeral sessions, 'cache' store can be sufficient but data will expire with TTL.
  - Use 'null' for tests where you intentionally do not want persistence.
- Error handling: persistMessage() logs and rethrows exceptions on failures to write to the store, allowing upstream handling.
- Summaries: the built-in summarization method is a hook; wire up a background job or alternative trait/binding if you need automatic summary generation.
- Clearing data: clearConversation() deletes persisted data for the current conversation and resets in-memory state—use cautiously.
- Diagnostics: extensive logs in hydration and persistence flows can be reviewed in your application log (e.g., storage/logs/laravel.log) to troubleshoot.

### FAQ
- Does enabling agent definition persistence also enable conversation history persistence?
  - No. They are separate systems. You must call withConversation() to persist chat history.

- Can I choose a different store per environment?
  - Yes. Use env vars AGENT_PERSISTENCE_STORE (database|cache|null) and DB/CACHE env settings accordingly.

- How are messages ordered when hydrating?
  - Persisted messages are sorted by created_at ascending, then appended after any preserved system/developer messages.

- What happens to existing in-memory user/assistant messages when hydrating?
  - Only system/developer messages are preserved; the rest is replaced by the persisted history.

- How do I retrieve the persisted history manually?
  - Call getPersistedHistory($limit). It returns [] if persistence is not enabled or no conversation id is set.

### Summary
- Persistence is opt-in per agent and controlled by a global switch.
- Agent definition persistence and conversation persistence are distinct features.
- withConversation() is the entry point for a persistent chat: it sets an id, ensures a store and context strategy, creates the record, and hydrates history while preserving system/developer prompts.
- Messages are persisted as you chat; you can resume later by reloading the agent and supplying the conversation id.
- Configuration allows you to switch stores, control context size, and hook summarization.
