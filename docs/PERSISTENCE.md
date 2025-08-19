### Optional Conversation Persistence (Stateless by default)

By default, agents are stateless and keep message history only in memory within the PHP process. No database or cache is used unless you explicitly enable persistence per agent. This preserves current behavior for all existing code.

This section shows how to optionally persist conversations so you can resume them later.

---

#### Key ideas

- Default remains stateless: no DB or cache needed, nothing changes unless you opt in.
- Opt-in per agent with `withConversation()`: persistence is only active for that agent instance.
- You control the store via config/env (database, cache, or null). The default is `null` to remain stateless.
- Minimal context hydration: persisted context is injected safely (won’t overwrite your developer/system prompts) and recent messages are appended to the in-memory history.

---

### Quick start: enable persistence per agent

```php
use Sapiensly\OpenaiAgents\Facades\Agent;

// 1) Create an agent and opt-in to persistence
$agent = Agent::agent()->withConversation(); // auto-generates a UUID conversation id

// Optional: provide your own conversation id (e.g., per user/session)
$agent = Agent::agent()->withConversation('user-123-session-abc');

// 2) Use as usual
$response = $agent->chat('Hello, can you remember this?');

// 3) Get the conversation id to resume later
$conversationId = $agent->getConversationId();
// Persist $conversationId somewhere you control (e.g., DB, session, cookie).
```

Resuming later (e.g., in a different request):

```php
$agent = Agent::agent()->withConversation($conversationId);
$response = $agent->chat('What did I say previously?');
```

Notes:
- The agent persists both user and assistant messages when you call `chat()`.
- If persistence is globally disabled or the store is set to `null`, `withConversation()` will still work but nothing gets saved (no-ops), keeping behavior stateless.

---

### Inspecting and managing persistent history

- Get in-memory messages (will auto-hydrate from persistence if empty):
  ```php
  $messages = $agent->getMessages();
  ```

- Get persisted history directly (bypasses in-memory limit), with a limit:
  ```php
  $history = $agent->getPersistedHistory(50);
  ```

- Clear and delete the persisted conversation:
  ```php
  $agent->clearConversation(); // deletes persisted data for this conversation id and resets agent state
  ```

---

### How context hydration works

When you call `withConversation($id)` and persistence is enabled:
- The trait loads a compact “Persisted Context” system note built from:
  - An optional summary (if you have one stored),
  - The most recent messages (bounded by config).
- It then appends the most recent messages to the in-memory history, respecting your `max_turns` setting.
- If your Agent supports `appendInstructions()`, the context is added as an appended instruction; otherwise, it’s injected as a system message. Your own instructions are preserved.

---

### Configuration

Publish config (optional, recommended):

- Config file tag:
  - `php artisan vendor:publish --tag=agent-persistence-config`
- Migrations tag (if using the database store):
  - `php artisan vendor:publish --tag=agent-persistence-migrations`
  - `php artisan migrate`

Relevant `.env` (defaults shown):

```
AGENT_PERSISTENCE_ENABLED=true
AGENT_PERSISTENCE_STORE=null  # options: null | database | cache (cache optional, see below)
```

`config/agent-persistence.php` highlights:

- Global switch (still requires `withConversation()` per agent):
  - `'enabled' => env('AGENT_PERSISTENCE_ENABLED', true)`
- Default store (null keeps everything stateless by default):
  - `'default' => env('AGENT_PERSISTENCE_STORE', 'null')`
- Context strategy (used to build compact “Persisted Context”):
  - `'context.strategy' => RecentMessagesStrategy::class`
  - `'context.max_messages' => 20`
  - `'context.include_summary' => true`
- Summarization options are present but no auto-generation is enforced by default.

---

### Stores

- `null` (default) — No-ops. Safe, no external dependencies, preserves in-memory behavior.
- `database` — Persists conversations/messages in your DB with optional cache acceleration.
  - Set `AGENT_PERSISTENCE_STORE=database`
  - Publish and run migrations.
  - Uses Conversation and Message models/tables; includes minimal caching of messages/summary.
- `cache` — Optional. If a `CacheStore` class is available in your setup, you can use `AGENT_PERSISTENCE_STORE=cache`. If not available, it gracefully falls back to `null`.

Service provider binds `ConversationStore` based on config; if a store class isn’t present, it automatically falls back to `NullStore` to keep behavior safe.

---

### Example: Database store setup

1) Enable database store in `.env`:
```
AGENT_PERSISTENCE_STORE=database
```

2) Publish config and migrations:
```
php artisan vendor:publish --tag=agent-persistence-config
php artisan vendor:publish --tag=agent-persistence-migrations
php artisan migrate
```

3) Use `withConversation()` in your code:
```php
$agent = Agent::agent()->withConversation('user-42');
// from now on, messages are stored in your DB
```

---

### Controlling how much context is injected

Use config values to tune the injected context size:

- `agent-persistence.context.max_messages`: cap of recent messages in the “Persisted Context” block.
- `agent-persistence.context.include_summary`: include a stored summary if available.

You can swap the context builder by changing:
- `'context.strategy' => YourCustomStrategy::class`

Implement `Sapiensly\OpenaiAgents\Persistence\Strategies\ContextStrategy` and return a compact string.

---

### Summarization

The trait includes hooks for summarization but does not generate summaries by default (to avoid extra model deps). You can:
- Generate summaries in a queue job when message counts hit thresholds.
- Store them by calling `ConversationStore::updateSummary($id, $summary)`.
- They will be auto-injected (if `include_summary` is true) to reduce token usage while maintaining context.

---

### Backward compatibility

- If you never call `withConversation()`, nothing changes.
- If `AGENT_PERSISTENCE_STORE` is `null` (default), even with `withConversation()` there’s no DB/cache requirement and no persisted writes.
- Persistence logic is additive and non-breaking, respecting your existing instructions and message flow.

---

### Troubleshooting

- “Nothing persists”: Check `.env` has `AGENT_PERSISTENCE_ENABLED=true` and `AGENT_PERSISTENCE_STORE=database`, migrations ran, and you’re calling `withConversation()`.
- “Context seems missing”: Ensure you call `withConversation($id)` before `chat()`, and that your store returns messages for that id.
- “Too many tokens”: Tune `agent-persistence.context.*` settings and your agent’s `max_turns`/`max_conversation_tokens`.
- “Cache tags not available”: The DB store will still work; it simply won’t use cache tags if your cache driver doesn’t support them.

---

### Minimal usage patterns

- Stateless (default):
  ```php
  $agent = Agent::agent();
  $agent->chat('Hello'); // in-memory only
  ```

- Persisted:
  ```php
  $agent = Agent::agent()->withConversation('session-xyz');
  $agent->chat('Please remember this.');
  // Later (new request)
  Agent::agent()->withConversation('session-xyz')->chat('What did I ask you to remember?');
  ```

This adds persistence as an optional, explicit feature while keeping the library stateless by default.