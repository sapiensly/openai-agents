# Configurable Persistence for Sapiensly/OpenAI Agents

Author: Junie (JetBrains)
Updated: 2025-08-17
Status: Draft – Implementation Plan

## 1) Purpose and Scope

Add an opt-in, configurable persistence layer for conversations and messages that:
- Extends the existing Agent/AgentManager without breaking current behavior.
- Uses Laravel conventions (Service Providers, Config, Events, Eloquent, Caching).
- Works across the package’s progressive levels (1–4) and features (RAG, functions, web search, MCP, Runner).
- Provides multiple backends through a ConversationStore contract (DB, Cache, Null).

Non-goals (for this phase):
- Data encryption at rest, multi-tenant isolation guarantees, export/import UIs, or analytics dashboards. These are planned for later phases.

## 2) Current State (Inventory)

Found in codebase:
- Trait: src/Persistence/PersistentAgentTrait.php (withConversation, getConversationId, getPersistedHistory, loadPersistedContext, persistMessage, maybeSummarize placeholders)
- Contracts: src/Persistence/Contracts/ConversationStore.php
- Stores: src/Persistence/Stores/DatabaseStore.php, src/Persistence/Stores/NullStore.php
- Strategies: src/Persistence/Strategies/ContextStrategy.php, src/Persistence/Strategies/RecentMessagesStrategy.php
- Models: src/Persistence/Models/Conversation.php, src/Persistence/Models/Message.php
- Config: config/agent-persistence.php (enabled, default store, stores, context, summarization, cleanup)
- Providers: src/PersistenceServiceProvider.php (binds ConversationStore, ContextStrategy; publishes config/migrations), src/AgentServiceProvider.php registers PersistenceServiceProvider
- AgentManager: persistent(), continueConversation(), newConversation() convenience methods
- Facade: Facades/Agent.php docblock already advertises persistent/newConversation/continueConversation
- Agent: uses PersistentAgentTrait. Event metadata (fireResponseEvent/fireErrorEvent) updated to include conversation_id, persistence_enabled (added in this PR).

Missing or to be completed:
- CacheStore (optional in Phase 1, recommended in Phase 2)
- SummaryStrategy (optional; summarization hook currently a placeholder in trait)
- Database migration file for agent_conversations and agent_messages (provider publishes if present; likely missing)
- Hooks in Agent::chat/chatStreamed to actually call persistMessage() for user/assistant (currently the trait defines persistence methods but Agent does not call them)

## 3) Target Architecture (from implementing-persistence.md, adapted to current repo)

Directory structure (already partially present):
- src/Persistence/
  - Contracts/ConversationStore.php ✓
  - Stores/DatabaseStore.php ✓, Stores/CacheStore.php (TBD), Stores/NullStore.php ✓
  - Models/Conversation.php ✓, Models/Message.php ✓
  - Strategies/ContextStrategy.php ✓, Strategies/RecentMessagesStrategy.php ✓, Strategies/SummaryStrategy.php (TBD)
  - PersistentAgentTrait.php ✓
- config/agent-persistence.php ✓
- database/migrations/2024_01_01_000000_create_agent_conversations_tables.php (TBD)
- PersistenceServiceProvider.php ✓ integrated via AgentServiceProvider

Key behaviors:
- Opt-in persistence via $agent->withConversation($id?) and global enable flag config('agent-persistence.enabled').
- ConversationStore binding resolved by config('agent-persistence.default') – default to null to preserve current behavior.
- ContextStrategy builds compact context from summary/recent messages and is injected into Agent state.
- Summarization hook is optional and offloaded to later iterations or jobs.

## 4) Implementation Roadmap

Phase 1 – Core (ship-ready with DB store)
1. Agent hooks for persistence
   - Modify Agent::chat() to:
     - If persistence enabled: persist user message via $this->persistMessage('user', $message)
     - Keep existing memory behavior; then delegate to chatWithResponsesAPI()
     - After getting response string: persist assistant message with token usage and model metadata
   - Modify Agent::chatStreamed() (if implemented) similarly: persist user message up-front; accumulate chunks and persist assistant response at the end
   - Ensure tool/developer messages are also persisted when produced (Phase 1.5 or Phase 2)

2. ConversationStore completeness
   - Keep DatabaseStore (existing), NullStore (existing)
   - Provide migration file to back DatabaseStore
   - Ensure DatabaseStore uses cache tags where available (already implemented)

3. Context Strategy
   - Use RecentMessagesStrategy by default; allow override via config
   - Maintain non-destructive context injection (append instructions or add system message) – already implemented

4. Config & Providers
   - Keep default store as 'null' to ensure zero breaking changes
   - PersistenceServiceProvider binding is correct; verify it’s registered in AgentServiceProvider::boot() (yes)

5. Events integration
   - Include conversation_id and persistence_enabled in AgentResponseGenerated metadata (done both for success and error)

6. DB migrations and Models
   - Add a migration file for agent_conversations and agent_messages matching Models
   - Validate Models/Conversation.php and Models/Message.php against migration columns

Acceptance criteria (Phase 1):
- Existing apps see no behavior change by default
- When calling withConversation(), user and assistant messages are saved; getPersistedHistory() returns recent messages
- Context loads on withConversation() re-entry
- Event metadata includes conversation info

Phase 2 – Polish
1. Add CacheStore backend (pure cache-based persistence with TTL/prefix)
2. Summarization strategy
   - Add Strategies/SummaryStrategy.php, optional background job/queue
   - Configurable model/length/temperature; disable by default in plan or keep placeholder logic
3. Artisan commands
   - Cleanup (delete old conversations), list conversations, inspect summaries
4. Performance testing and optimizations (cache keys/tags)
5. DX documentation updates (WorkingREADME additions)

Phase 3 – Advanced
1. Multi-tenant support (scoping by tenant id)
2. Optional at-rest encryption for content/metadata
3. Export/import (JSONL) of conversations
4. Analytics/reporting hooks
5. Advanced context/summarization strategies (e.g., semantic compression)

## 5) Detailed Task Breakdown

A. Agent hooks (Core)
- Update src/Agent.php methods:
  - chat(string $message, ...):
    - if ($this->persistenceEnabled) $this->persistMessage('user', $message)
    - append in-memory message as today
    - $response = $this->chatWithResponsesAPI(...)
    - if ($this->persistenceEnabled) $this->persistMessage('assistant', $response, ['token_count' => $this->getTokenUsage(), 'model' => $this->options->get('model')])
  - chatStreamed(...): mirror behavior; persist complete response at end
- Optional: persist 'developer'/'tool' role outputs when used; include tool_call_id and name in metadata

B. Migration
- Add database/migrations/2024_01_01_000000_create_agent_conversations_tables.php with:
  - agent_conversations: uuid id PK, agent_id, user_id, title, summary (text), metadata (json), last_message_at, summary_updated_at, message_count (int), total_tokens (int), timestamps, soft deletes, indexes per spec
  - agent_messages: uuid id PK, conversation_id FK -> agent_conversations, role enum [system,user,assistant,developer,tool], content text, token_count int nullable, metadata json nullable, created_at timestamp, indexes
- Provider already publishes migration if present

C. CacheStore (Phase 2)
- New src/Persistence/Stores/CacheStore.php implementing ConversationStore
- Use configured cache store/ttl/prefix; keep structures consistent with DatabaseStore return shapes
- No SQL; all data in cache with proper invalidation and TTLs

D. Strategies/SummaryStrategy (Phase 2)
- Add interface or concrete strategy that can be injected via config if enabled
- Minimal approach: allow external job to update summary via ConversationStore->updateSummary()

E. Config alignment
- Keep default => 'null' (already set)
- Ensure context defaults (max_messages, include_summary) are honored – already used by RecentMessagesStrategy

F. Docs and DX
- Update WorkingREADME (separate PR) with persistence usage snippets:
  - $agent = Agent::newConversation(); $agent->chat(...); $id = $agent->getConversationId();
  - $agent = Agent::continueConversation($id); $agent->chat(...)
- Add code samples for streaming + persistence

G. Tests (outline)
- Unit: ConversationStore contract against NullStore and DatabaseStore (with sqlite)
- Feature: Agent with withConversation() persists and reloads messages
- Event: metadata includes conversation_id/persistence_enabled
- Runner: two agents with different conversations do not cross-pollinate history

## 6) Backward Compatibility
- Default store is 'null'; persistence only activates when calling withConversation()
- No behavior change for existing users unless they opt in
- Event metadata additions are additive

## 7) Risks & Mitigations
- DB dependency: Keep default store = null; migrations opt-in via publish
- Token usage accuracy: Persist as metadata; allow custom accounting later
- Large histories: Use strategy limits; encourage summarization in Phase 2

## 8) Step-by-Step Checklist

Core
- [ ] Add Agent::chat hooks for persistence (user/assistant)
- [ ] Add Agent::chatStreamed hooks
- [ ] Add migration file and publish tag (agent-persistence-migrations)
- [x] Event metadata includes conversation_id and persistence_enabled
- [x] Config/provider bindings validated

Polish
- [ ] CacheStore
- [ ] SummaryStrategy and optional background job
- [ ] Artisan cleanup/list commands
- [ ] Performance tests and adjustments

Advanced
- [ ] Multi-tenant support
- [ ] Encryption options
- [ ] Export/import
- [ ] Analytics and reporting hooks

## 9) Local Testing Guide

1) Ensure config published or rely on package defaults. Keep AGENT_PERSISTENCE_STORE=null until ready.
2) To test DB store:
   - Publish and run migrations (once added): php artisan vendor:publish --tag=agent-persistence-migrations && php artisan migrate
   - Set AGENT_PERSISTENCE_STORE=database
   - In code:
     ```php
     $agent = \Sapiensly\OpenaiAgents\Facades\Agent::newConversation();
     $reply1 = $agent->chat('Hello');
     $id = $agent->getConversationId();
     $agent2 = \Sapiensly\OpenaiAgents\Facades\Agent::continueConversation($id);
     $reply2 = $agent2->chat('What did I say?');
     $history = $agent2->getPersistedHistory();
     ```
3) Assertions: history contains both messages; event metadata includes conversation_id and persistence_enabled=true.

---

This plan follows implementing-persistence.md and reflects the current repository. Minimal code shipped here: event metadata alignment. The remaining tasks are clearly staged to avoid breaking changes and to fit Laravel package conventions.
