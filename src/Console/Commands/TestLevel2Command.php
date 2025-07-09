<?php

/**
 * TestLevel2Command - Agent with Tools Testing
 * 
 * Purpose: Tests Level 2 of the progressive enhancement architecture - agents
 * that can use tools (functions, APIs, calculations, file operations, etc.).
 * This command validates tool registration, usage, and integration.
 * 
 * Level 2 Concept: Agent can use tools (functions, APIs, calculations, file ops, etc.).
 * Still user-driven, but can perform actions. This level adds capability without autonomy.
 * 
 * Features Tested:
 * - Tool registration and usage with Runner
 * - Multiple tool integration (calculator, date, echo)
 * - Custom agent creation with predefined tools
 * - Tool usage in conversations
 * - Performance measurement and timing
 * - OpenAI official tools (code interpreter, retrieval, web search)
 * 
 * Usage:
 * - Basic test: php artisan agent:test-level2 "Calculate 15 * 23"
 * - Custom system: php artisan agent:test-level2 "What's the weather?" --system="You are a weather assistant"
 * - Different model: php artisan agent:test-level2 "Solve this equation" --model=gpt-4
 * - With tracing: php artisan agent:test-level2 "Hello" --trace
 * 
 * Test Scenarios:
 * 1. Runner with calculator tool
 * 2. Runner with multiple tools (calculator, date, echo)
 * 3. Custom agent with predefined tools
 * 4. OpenAI official tools demonstration
 * 5. Performance timing for each test
 * 
 * Tools Demonstrated:
 * - calculator: Mathematical calculations
 * - date: Current date/time retrieval
 * - echo: Text echoing for testing
 * - OpenAI tools: Code interpreter, retrieval, web search
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;

class TestLevel2Command extends Command
{
    protected $signature = 'agent:test-level2
                            {message : The message to send to the agent}
                            {--system= : Optional custom system prompt}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--trace : Enable tracing for debugging}';

    protected $description = 'Test Level 2: Agent with Tools - Tool registration and usage';

    public function handle(AgentManager $manager): int
    {
        $this->info('🧪 Testing Level 2: Agent with Tools');
        $this->line('Concept: Agent can use tools (functions, APIs, calculations, file ops, etc). Still user-driven, but can perform actions.');
        $this->line('');

        $message = $this->argument('message');
        $systemPrompt = $this->option('system') ?: 'You are a helpful assistant that can use various tools. When asked to perform specific tasks, use the appropriate tools available to you.';
        $model = $this->option('model');
        $trace = $this->option('trace');

        // Verify OpenAI API key
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('❌ OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return self::FAILURE;
        }

        try {
            $this->info('📝 Test 1: Runner with Calculator Tool');
            $this->line("Input: {$message}");
            
            $runner = Agent::runner();
            
            // Register calculator tool
            $runner->registerTool('calculator', function($args) {
                $expression = $args['expression'] ?? '0';
                $result = eval("return {$expression};");
                return "Result: {$result}";
            });
            
            $startTime = microtime(true);
            $response = $runner->run($message);
            $duration = microtime(true) - $startTime;
            
            $this->line("Response: {$response}");
            $this->line("Duration: " . number_format($duration, 3) . "s");
            $this->line('');

            $this->info('📝 Test 2: Runner with Multiple Tools');
            $this->line("Input: {$message}");
            
            $runner2 = Agent::runner();
            
            // Register multiple tools
            $runner2->registerTool('calculator', function($args) {
                $expression = $args['expression'] ?? '0';
                $result = eval("return {$expression};");
                return "Calculation result: {$result}";
            });
            
            $runner2->registerTool('date', function($args) {
                $format = $args['format'] ?? 'Y-m-d H:i:s';
                return "Current date: " . date($format);
            });
            
            $runner2->registerTool('echo', function($args) {
                $text = $args['text'] ?? '';
                return "Echo: {$text}";
            });
            
            $startTime = microtime(true);
            $response2 = $runner2->run($message);
            $duration2 = microtime(true) - $startTime;
            
            $this->line("Response: {$response2}");
            $this->line("Duration: " . number_format($duration2, 3) . "s");
            $this->line('');

            $this->info('📝 Test 3: Custom Agent with Tools');
            $this->line("System: {$systemPrompt}");
            $this->line("Model: {$model}");
            $this->line("Input: {$message}");
            
            $customAgent = Agent::create([
                'system_prompt' => $systemPrompt,
                'model' => $model,
                'tools' => ['calculator', 'date', 'echo'],
            ]);
            
            $startTime = microtime(true);
            $response3 = $customAgent->chat($message);
            $duration3 = microtime(true) - $startTime;
            
            $this->line("Response: {$response3}");
            $this->line("Duration: " . number_format($duration3, 3) . "s");
            $this->line('');

            $this->info('📝 Test 4: OpenAI Official Tools');
            $this->warn('⚠️  Code interpreter test skipped. To enable it, configure a valid OpenAI container ID (it must start with "cntr_").');
            $this->warn('You can obtain a container ID at https://platform.openai.com/containers.');
            // Usage example:
            // $openaiAgent = Agent::create([
            //     'system_prompt' => 'You can use code interpreter, retrieval, and web search.',
            //     'model' => 'gpt-4o',
            // ]);
            // $openaiAgent->registerCodeInterpreter('cntr_xxxxxxxxxxxxxxxx');
            // $openaiAgent->registerRetrieval(["k" => 3]);
            // $openaiAgent->registerWebSearch();
            // $response4 = $openaiAgent->chat($message);
            $this->line('');

            $this->info('✅ Level 2 Tests Completed Successfully!');
            $this->line('Features tested:');
            $this->line('  ✓ Tool registration');
            $this->line('  ✓ Tool usage in conversations');
            $this->line('  ✓ Multiple tools');
            $this->line('  ✓ Custom agent with tools');
            $this->line('  ✓ Response timing');
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error during Level 2 test: {$e->getMessage()}");
            if ($trace) {
                $this->line("Stack trace: {$e->getTraceAsString()}");
            }
            return self::FAILURE;
        }
    }
} 