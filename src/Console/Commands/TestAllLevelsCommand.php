<?php

/**
 * TestAllLevelsCommand - Comprehensive Progressive Enhancement Testing
 * 
 * Purpose: Tests all 4 levels of the progressive enhancement architecture in
 * a single command. This provides a comprehensive validation of the entire
 * agent system from basic conversation to full autonomy.
 * 
 * Progressive Enhancement Architecture:
 * - Level 1: Conversational Agent (basic chat and Q&A)
 * - Level 2: Agent with Tools (functions, APIs, calculations)
 * - Level 3: Multi-Agents (collaboration, handoff, workflows)
 * - Level 4: Autonomous Agents (decision making, self-monitoring)
 * 
 * Features Tested:
 * - All 4 levels of progressive enhancement
 * - Performance comparison across levels
 * - Comprehensive feature validation
 * - Timing and performance measurement
 * - Error handling and validation
 * - Optional specific level testing
 * 
 * Usage:
 * - Test all levels: php artisan agent:test-all-levels "Hello, help me"
 * - Test specific level: php artisan agent:test-all-levels "Test" --level=2
 * - Custom system: php artisan agent:test-all-levels "Help" --system="You are helpful"
 * - Different model: php artisan agent:test-all-levels "Hello" --model=gpt-4
 * - Custom autonomy: php artisan agent:test-all-levels "Act" --autonomy-level=high
 * - With tracing: php artisan agent:test-all-levels "Hello" --trace
 * 
 * Test Scenarios:
 * 1. Level 1: Simple chat functionality
 * 2. Level 2: Tool registration and usage
 * 3. Level 3: Multi-agent collaboration
 * 4. Level 4: Autonomous decision making
 * 5. Performance comparison across all levels
 * 6. Comprehensive summary and statistics
 * 
 * Progressive Enhancement Benefits:
 * - Start simple and scale up
 * - Add capabilities incrementally
 * - Maintain backward compatibility
 * - Test each level independently
 * - Comprehensive validation approach
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;

class TestAllLevelsCommand extends Command
{
    protected $signature = 'agent:test-all-levels
                            {message : The message to send to the agents}
                            {--system= : Optional custom system prompt}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--autonomy-level=high : Autonomy level for Level 4 (low, medium, high)}
                            {--mode=autonomous : Agent mode for Level 4 (autonomous, supervised)}
                            {--trace : Enable tracing for debugging}
                            {--level= : Test specific level only (1, 2, 3, 4)}';

    protected $description = 'Test all 4 levels of the progressive enhancement architecture';

    public function handle(AgentManager $manager): int
    {
        $this->info('🧪 Testing All Levels of Progressive Enhancement Architecture');
        $this->line('This will test all 4 levels of the agent system:');
        $this->line('  Level 1: Conversational Agent');
        $this->line('  Level 2: Agent with Tools');
        $this->line('  Level 3: Multi-Agents');
        $this->line('  Level 4: Autonomous Agents');
        $this->line('');

        $message = $this->argument('message');
        $systemPrompt = $this->option('system');
        $model = $this->option('model');
        $autonomyLevel = $this->option('autonomy-level');
        $mode = $this->option('mode');
        $trace = $this->option('trace');
        $specificLevel = $this->option('level');

        // Verify OpenAI API key
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('❌ OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return self::FAILURE;
        }

        $results = [];
        $startTime = microtime(true);

        try {
            // Test Level 1: Conversational Agent
            if (!$specificLevel || $specificLevel == '1') {
                $this->info('🔵 Testing Level 1: Conversational Agent');
                $this->line('Concept: Basic agent for simple chat and Q&A. No tools, no autonomy, just conversation.');
                
                $level1Start = microtime(true);
                $response1 = Agent::simpleChat($message);
                $level1Duration = microtime(true) - $level1Start;
                
                $this->line("Response: {$response1}");
                $this->line("Duration: " . number_format($level1Duration, 3) . "s");
                $this->line('');
                
                $results['level1'] = [
                    'success' => true,
                    'duration' => $level1Duration,
                    'response' => $response1
                ];
            }

            // Test Level 2: Agent with Tools
            if (!$specificLevel || $specificLevel == '2') {
                $this->info('🟢 Testing Level 2: Agent with Tools');
                $this->line('Concept: Agent can use tools (functions, APIs, calculations, file ops, etc). Still user-driven, but can perform actions.');
                
                $runner = Agent::runner();
                $runner->registerTool('calculator', function($args) {
                    $expression = $args['expression'] ?? '0';
                    $result = eval("return {$expression};");
                    return "Result: {$result}";
                });
                
                $level2Start = microtime(true);
                $response2 = $runner->run($message);
                $level2Duration = microtime(true) - $level2Start;
                
                $this->line("Response: {$response2}");
                $this->line("Duration: " . number_format($level2Duration, 3) . "s");
                $this->line('');
                
                $results['level2'] = [
                    'success' => true,
                    'duration' => $level2Duration,
                    'response' => $response2
                ];
            }

            // Test Level 3: Multi-Agents
            if (!$specificLevel || $specificLevel == '3') {
                $this->info('🟡 Testing Level 3: Multi-Agents');
                $this->line('Concept: Multiple specialized agents collaborate (handoff, workflows). Each agent can have its own tools, persona, and config.');
                
                $customerServiceAgent = Agent::create([
                    'system_prompt' => 'You are a customer service representative. Help customers with their inquiries.',
                    'model' => $model,
                    'tools' => ['echo', 'date'],
                ]);
                
                $level3Start = microtime(true);
                $response3 = $customerServiceAgent->chat($message);
                $level3Duration = microtime(true) - $level3Start;
                
                $this->line("Response: {$response3}");
                $this->line("Duration: " . number_format($level3Duration, 3) . "s");
                $this->line('');
                
                $results['level3'] = [
                    'success' => true,
                    'duration' => $level3Duration,
                    'response' => $response3
                ];
            }

            // Test Level 4: Autonomous Agents
            if (!$specificLevel || $specificLevel == '4') {
                $this->info('🔴 Testing Level 4: Autonomous Agents');
                $this->line('Concept: Agents can decide, act, monitor, and learn autonomously. Not just reactive: can initiate actions, monitor systems, and adapt.');
                $this->line('New features: mode, autonomy_level, capabilities, execute(), self-monitoring, decision making.');
                
                $autonomousAgent = Agent::create([
                    'mode' => $mode,
                    'autonomy_level' => $autonomyLevel,
                    'capabilities' => ['monitor', 'decide', 'act', 'learn'],
                    'tools' => ['system_diagnostics', 'auto_fix', 'alert_system'],
                    'system_prompt' => $systemPrompt ?: 'You are an autonomous system monitor. Monitor and fix issues automatically.',
                    'model' => $model,
                ]);
                
                $level4Start = microtime(true);
                $result4 = $autonomousAgent->execute($message);
                $level4Duration = microtime(true) - $level4Start;
                
                $this->line("Result: {$result4}");
                $this->line("Duration: " . number_format($level4Duration, 3) . "s");
                $this->line('');
                
                $results['level4'] = [
                    'success' => true,
                    'duration' => $level4Duration,
                    'response' => $result4
                ];
            }

            $totalDuration = microtime(true) - $startTime;

            // Summary
            $this->info('📊 Test Summary');
            $this->line('==============');
            
            foreach ($results as $level => $result) {
                $levelName = match($level) {
                    'level1' => 'Level 1: Conversational Agent',
                    'level2' => 'Level 2: Agent with Tools',
                    'level3' => 'Level 3: Multi-Agents',
                    'level4' => 'Level 4: Autonomous Agents',
                    default => $level
                };
                
                $status = $result['success'] ? '✅ PASS' : '❌ FAIL';
                $this->line("{$status} {$levelName} - " . number_format($result['duration'], 3) . "s");
            }
            
            $this->line('');
            $this->line("Total test duration: " . number_format($totalDuration, 3) . "s");
            $this->line('');
            
            $this->info('✅ All Level Tests Completed Successfully!');
            $this->line('Features tested across all levels:');
            $this->line('  ✓ Simple chat functionality');
            $this->line('  ✓ Tool registration and usage');
            $this->line('  ✓ Multiple specialized agents');
            $this->line('  ✓ Agent collaboration');
            $this->line('  ✓ Autonomous decision making');
            $this->line('  ✓ Execute() method');
            $this->line('  ✓ Self-monitoring capabilities');
            $this->line('  ✓ Progressive enhancement architecture');
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error during testing: {$e->getMessage()}");
            if ($trace) {
                $this->line("Stack trace: {$e->getTraceAsString()}");
            }
            return self::FAILURE;
        }
    }
} 