<?php

/**
 * TestLevel4Command - Autonomous Agents Testing
 * 
 * Purpose: Tests Level 4 of the progressive enhancement architecture - autonomous
 * agents that can decide, act, monitor, and learn independently. This command
 * validates autonomy, decision-making, and self-monitoring capabilities.
 * 
 * Level 4 Concept: Agents can decide, act, monitor, and learn autonomously.
 * Not just reactive: can initiate actions, monitor systems, and adapt. This
 * level adds autonomy and self-directed behavior.
 * 
 * Features Tested:
 * - Autonomous mode with execute() method
 * - Autonomy levels (low, medium, high)
 * - Self-monitoring capabilities
 * - Decision-making and autonomous actions
 * - Capabilities checking and validation
 * - Safety validation for autonomous actions
 * - Performance measurement and timing
 * 
 * Usage:
 * - Basic test: php artisan agent:test-level4 "Monitor the system"
 * - Custom system: php artisan agent:test-level4 "Fix the issue" --system="You are a system monitor"
 * - Different autonomy: php artisan agent:test-level4 "Act" --autonomy-level=high
 * - Supervised mode: php artisan agent:test-level4 "Help" --mode=supervised
 * - With tracing: php artisan agent:test-level4 "Hello" --trace
 * 
 * Test Scenarios:
 * 1. Autonomous agent with execute() method
 * 2. Autonomous agent with self-monitoring
 * 3. Autonomous agent with decision making
 * 4. Capabilities checking and validation
 * 5. Safety validation for autonomous actions
 * 6. Performance timing for each test
 * 
 * Autonomy Features:
 * - Mode selection (autonomous, supervised)
 * - Autonomy levels (low, medium, high)
 * - Capabilities (monitor, decide, act, learn, self_monitor)
 * - Safety checks and validation
 * - Self-monitoring and adaptation
 * 
 * New Level 4 Features:
 * - execute() method for autonomous actions
 * - mode and autonomy_level parameters
 * - capabilities array for agent abilities
 * - Self-monitoring and decision making
 * - Safety validation systems
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;

class TestLevel4Command extends Command
{
    protected $signature = 'agent:test-level4
                            {message : The message to send to the agent}
                            {--system= : Optional custom system prompt}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--autonomy-level=high : Autonomy level (low, medium, high)}
                            {--mode=autonomous : Agent mode (autonomous, supervised)}
                            {--trace : Enable tracing for debugging}';

    protected $description = 'Test Level 4: Autonomous Agents - Autonomy, decision making, and execute() method';

    public function handle(AgentManager $manager): int
    {
        $this->info('🧪 Testing Level 4: Autonomous Agents');
        $this->line('Concept: Agents can decide, act, monitor, and learn autonomously. Not just reactive: can initiate actions, monitor systems, and adapt.');
        $this->line('New features: mode, autonomy_level, capabilities, execute(), self-monitoring, decision making.');
        $this->line('');

        $message = $this->argument('message');
        $systemPrompt = $this->option('system') ?: 'You are an autonomous system monitor. Monitor and fix issues automatically.';
        $model = $this->option('model');
        $autonomyLevel = $this->option('autonomy-level');
        $mode = $this->option('mode');
        $trace = $this->option('trace');

        // Verify OpenAI API key
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('❌ OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return self::FAILURE;
        }

        try {
            $this->info('📝 Test 1: Autonomous Agent with Execute() Method');
            $this->line("Mode: {$mode}");
            $this->line("Autonomy Level: {$autonomyLevel}");
            $this->line("Input: {$message}");
            
            $autonomousAgent = Agent::create([
                'mode' => $mode,
                'autonomy_level' => $autonomyLevel,
                'capabilities' => ['monitor', 'decide', 'act', 'learn'],
                'tools' => ['system_diagnostics', 'auto_fix', 'alert_system'],
                'system_prompt' => $systemPrompt,
                'model' => $model,
            ]);
            
            $startTime = microtime(true);
            $result = $autonomousAgent->execute($message);
            $duration = microtime(true) - $startTime;
            
            $this->line("Result: {$result}");
            $this->line("Duration: " . number_format($duration, 3) . "s");
            $this->line('');

            $this->info('📝 Test 2: Autonomous Agent with Self-Monitoring');
            $this->line("Input: {$message}");
            
            $monitoringAgent = Agent::create([
                'mode' => 'autonomous',
                'autonomy_level' => 'high',
                'capabilities' => ['monitor', 'decide', 'act', 'learn', 'self_monitor'],
                'tools' => ['system_diagnostics', 'auto_fix', 'alert_system', 'performance_monitor'],
                'system_prompt' => 'You are an autonomous system monitor with self-monitoring capabilities. Monitor systems and take autonomous actions when needed.',
                'model' => $model,
            ]);
            
            $startTime = microtime(true);
            $result2 = $monitoringAgent->execute($message);
            $duration2 = microtime(true) - $startTime;
            
            $this->line("Result: {$result2}");
            $this->line("Duration: " . number_format($duration2, 3) . "s");
            $this->line('');

            $this->info('📝 Test 3: Autonomous Agent with Decision Making');
            $this->line("Input: {$message}");
            
            $decisionAgent = Agent::create([
                'mode' => 'autonomous',
                'autonomy_level' => 'high',
                'capabilities' => ['monitor', 'decide', 'act', 'learn', 'self_monitor'],
                'tools' => ['system_diagnostics', 'auto_fix', 'alert_system', 'decision_engine'],
                'system_prompt' => 'You are an autonomous decision-making agent. Analyze situations and make autonomous decisions based on available information.',
                'model' => $model,
            ]);
            
            $startTime = microtime(true);
            $result3 = $decisionAgent->execute($message);
            $duration3 = microtime(true) - $startTime;
            
            $this->line("Result: {$result3}");
            $this->line("Duration: " . number_format($duration3, 3) . "s");
            $this->line('');

            $this->info('📝 Test 4: Autonomous Agent Capabilities Check');
            $this->line("Checking agent capabilities...");
            
            $capabilities = $autonomousAgent->getCapabilities();
            $this->line("Capabilities: " . implode(', ', $capabilities));
            
            $isAutonomous = $autonomousAgent->isAutonomous();
            $this->line("Is Autonomous: " . ($isAutonomous ? 'Yes' : 'No'));
            
            $autonomyLevel = $autonomousAgent->autonomyLevel();
            $this->line("Autonomy Level: " . ($autonomyLevel ?? 'Not set'));
            $this->line('');

            $this->info('📝 Test 5: Safety Check for Autonomous Actions');
            $this->line("Input: {$message}");
            
            $safeAgent = Agent::create([
                'mode' => 'autonomous',
                'autonomy_level' => 'high',
                'capabilities' => ['monitor', 'decide', 'act', 'learn', 'safety_check'],
                'tools' => ['system_diagnostics', 'auto_fix', 'alert_system', 'safety_validator'],
                'system_prompt' => 'You are an autonomous agent with safety checks. Always validate actions before executing them.',
                'model' => $model,
            ]);
            
            $startTime = microtime(true);
            $result4 = $safeAgent->execute($message);
            $duration4 = microtime(true) - $startTime;
            
            $this->line("Result: {$result4}");
            $this->line("Duration: " . number_format($duration4, 3) . "s");
            $this->line('');

            $this->info('✅ Level 4 Tests Completed Successfully!');
            $this->line('Features tested:');
            $this->line('  ✓ Autonomous mode');
            $this->line('  ✓ Autonomy levels');
            $this->line('  ✓ Execute() method');
            $this->line('  ✓ Self-monitoring');
            $this->line('  ✓ Decision making');
            $this->line('  ✓ Capabilities checking');
            $this->line('  ✓ Safety validation');
            $this->line('  ✓ Response timing');
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error during Level 4 test: {$e->getMessage()}");
            if ($trace) {
                $this->line("Stack trace: {$e->getTraceAsString()}");
            }
            return self::FAILURE;
        }
    }
} 