# Progressive Enhancement Architecture

## Overview

The Laravel OpenAI Agents package implements a **4-level progressive enhancement architecture** that allows developers to start with simple, one-line interactions and progressively add complexity as their needs grow. This approach follows the principle of "start simple, scale smart" - making the package accessible to beginners while maintaining the full power for advanced use cases.

## Level Progression

```
Level 1: Conversational Agent → Level 2: Agent with Tools → Level 3: Multi-Agents → Level 4: Autonomous Agents
     ↓                              ↓                           ↓                          ↓
Simple chat                  Tool integration           Multi-agent collaboration    Full autonomy
```

---

## Level 1: Conversational Agent

**Concept:**
- Basic agent for simple chat and Q&A.
- No tools, no autonomy, just conversation.

**Use Case:**
- Prototyping, support bots, FAQ, onboarding flows.

**Example:**
```php
$response = Agent::simpleChat('Hello world');
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

**Use Case:**
- Calculators, weather bots, file readers, API wrappers.

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

**Use Case:**
- Customer support with escalation, sales + tech support, workflow automation.

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

**Use Case:**
- System monitoring, self-healing bots, automated operations, proactive assistants.

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

## Conceptual Differences Table

| Level | Concept | Autonomy | Tools | Collaboration | Example Use Case |
|-------|---------|----------|-------|---------------|------------------|
| 1     | Conversational | No | No | No | FAQ bot, onboarding |
| 2     | With Tools     | No | Yes | No | Calculator, API bot |
| 3     | Multi-Agents   | No | Yes | Yes | Support + Sales bot |
| 4     | Autonomous     | Yes | Yes | Yes | Self-healing system |

---

## Migration Path

- **Start at Level 1** for simple chat.
- **Move to Level 2** when you need tools.
- **Upgrade to Level 3** for multi-agent workflows.
- **Adopt Level 4** for full autonomy and automation.

---

## Best Practices

- Use the lowest level that solves your problem.
- Only enable autonomy if you trust the agent's actions.
- Use `autonomy_level` to control risk: 'low' = suggest, 'medium' = safe actions, 'high' = full autonomy.
- Always monitor and log autonomous actions in production.

---

## Advanced: Level 4 API Reference

- `mode` (`string`): Set to `'autonomous'` to enable autonomous mode.
- `autonomy_level` (`string`): `'low'`, `'medium'`, or `'high'`.
- `capabilities` (`array`): List of agent skills (e.g., `['monitor', 'act', 'learn']`).
- `execute($task, $context = [])`: Run a task with decision logic and self-monitoring.
- `isAutonomous()`: Returns `true` if agent is in autonomous mode.
- `autonomyLevel()`: Returns the autonomy level.
- `getCapabilities()`: Returns the agent's capabilities.

---

This architecture ensures you can start simple and scale up as your needs grow, with a clear conceptual and technical path for each level. 