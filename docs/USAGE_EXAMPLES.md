# Usage Examples - Progressive Enhancement

This document demonstrates how to use the Laravel OpenAI Agents package at each progressive enhancement level, from simple to advanced.

---

## Level 1: Conversational Agent

**Concept:**
- Basic agent for simple chat and Q&A.
- No tools, no autonomy, just conversation.

**Example:**
```php
$response = Agent::simpleChat('Hello world');
echo $response;
```
**Config:**
```php
'progressive' => [
    'level' => 1,
    'auto_configure' => true,
],
```

---

## Level 2: Agent with Tools

**Concept:**
- Agent can use tools (functions, APIs, calculations, file ops, etc).
- Still user-driven, but can perform actions.

**Example:**
```php
$runner = Agent::runner();
$runner->registerTool('calculator', fn($args) => eval("return {$args['expression']};"));
$response = $runner->run('Calculate 15 * 23');
```
**Config:**
```php
'progressive' => [
    'level' => 2,
    'auto_tools' => true,
    'default_tools' => ['calculator', 'date'],
],
```

---

## Level 3: Multi-Agents

**Concept:**
- Multiple specialized agents collaborate (handoff, workflows).
- Each agent can have its own tools, persona, and config.

**Example:**
```php
$runner = Agent::runner();
$runner->setHandoffOrchestrator(app(HandoffOrchestrator::class));
$response = $runner->run('I need technical help and pricing');
```
**Config:**
```php
'progressive' => [
    'level' => 3,
    'multi_agents' => true,
    'auto_handoff' => true,
],
```

---

## Level 4: Autonomous Agents

**Concept:**
- Agents can decide, act, monitor, and learn autonomously.
- Not just reactive: can initiate actions, monitor systems, and adapt.
- New features: `mode`, `autonomy_level`, `capabilities`, `execute()`, self-monitoring, decision making.

**Example:**
```php
$agent = Agent::create([
    'mode' => 'autonomous',
    'autonomy_level' => 'high',
    'capabilities' => ['monitor', 'decide', 'act', 'learn'],
    'tools' => ['system_diagnostics', 'auto_fix', 'alert_system'],
    'system_prompt' => 'You are an autonomous system monitor. Monitor and fix issues automatically.',
]);

$result = $agent->execute('Monitor system and fix issues automatically');
echo $result; // [AUTONOMOUS] Executed: Monitor system and fix issues automatically [approved]
```
**Config:**
```php
'progressive' => [
    'level' => 4,
    'autonomy_enabled' => true,
    'enterprise_features' => true,
],
```

---

## Environment Configuration

### .env Settings
```env
# Level 1: Conversational
AGENTS_PROGRESSIVE_LEVEL=1

# Level 2: With Tools  
AGENTS_PROGRESSIVE_LEVEL=2
AGENTS_AUTO_TOOLS=true

# Level 3: Multi-Agents
AGENTS_PROGRESSIVE_LEVEL=3
AGENTS_MULTI_AGENTS=true
AGENTS_AUTO_HANDOFF=true

# Level 4: Autonomous
AGENTS_PROGRESSIVE_LEVEL=4
AGENTS_AUTONOMY_ENABLED=true
AGENTS_ENTERPRISE_FEATURES=true
```

---

This progressive enhancement approach allows you to start simple and scale up as your needs grow, without breaking existing functionality. 