<?php

/**
 * TestLevel1Command - Conversational Agent Testing
 * 
 * Purpose: Tests Level 1 of the progressive enhancement architecture - basic
 * conversational agents without tools or autonomy. This command validates simple
 * chat functionality, custom system prompts, and model selection.
 * 
 * Level 1 Concept: Basic agent for simple chat and Q&A. No tools, no autonomy,
 * just conversation. This is the foundation level that all other levels build upon.
 * 
 * Features Tested:
 * - Simple chat functionality with Agent::simpleChat()
 * - Custom system prompts for different personas
 * - Model selection (gpt-3.5-turbo, gpt-4, etc.)
 * - Response timing and performance measurement
 * - Error handling and API key validation
 * 
 * Usage:
 * - Basic test: php artisan agent:test-level1 "Hello, how are you?"
 * - Custom system: php artisan agent:test-level1 "Tell me a joke" --system="You are a comedian"
 * - Different model: php artisan agent:test-level1 "Explain quantum physics" --model=gpt-4
 * - With tracing: php artisan agent:test-level1 "Hello" --trace
 * 
 * Test Scenarios:
 * 1. Simple chat with default settings
 * 2. Chat with custom system prompt
 * 3. Chat with different model selection
 * 4. Performance timing for each test
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;

class TestLevel1Command extends Command
{
    protected $signature = 'agent:test-level1
                            {message : The message to send to the agent}
                            {--system= : Optional custom system prompt}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--trace : Enable tracing for debugging}';

    protected $description = 'Test Level 1: Conversational Agent - Basic chat functionality';

    public function handle(AgentManager $manager): int
    {
        $this->info('🧪 Testing Level 1: Conversational Agent');
        $this->line('Concept: Basic agent for simple chat and Q&A. No tools, no autonomy, just conversation.');
        $this->line('');

        $message = $this->argument('message');
        $systemPrompt = $this->option('system') ?: 'You are a helpful conversational assistant.';
        $model = $this->option('model');
        $trace = $this->option('trace');

        // Verify OpenAI API key
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('❌ OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return self::FAILURE;
        }

        try {
            $this->info('📝 Test 1: Simple Chat');
            $this->line("Input: {$message}");
            
            $startTime = microtime(true);
            $response = Agent::simpleChat($message);
            $duration = microtime(true) - $startTime;
            
            $this->line("Response: {$response}");
            $this->line("Duration: " . number_format($duration, 3) . "s");
            $this->line('');

            $this->info('📝 Test 2: Agent with Custom System Prompt');
            $this->line("System: {$systemPrompt}");
            $this->line("Input: {$message}");
            
            $startTime = microtime(true);
            $response2 = Agent::simpleChat($message, ['system_prompt' => $systemPrompt]);
            $duration2 = microtime(true) - $startTime;
            
            $this->line("Response: {$response2}");
            $this->line("Duration: " . number_format($duration2, 3) . "s");
            $this->line('');

            $this->info('📝 Test 3: Agent with Custom Model');
            $this->line("Model: {$model}");
            $this->line("Input: {$message}");
            
            $startTime = microtime(true);
            $response3 = Agent::simpleChat($message, ['model' => $model]);
            $duration3 = microtime(true) - $startTime;
            
            $this->line("Response: {$response3}");
            $this->line("Duration: " . number_format($duration3, 3) . "s");
            $this->line('');

            $this->info('✅ Level 1 Tests Completed Successfully!');
            $this->line('Features tested:');
            $this->line('  ✓ Simple chat functionality');
            $this->line('  ✓ Custom system prompts');
            $this->line('  ✓ Model selection');
            $this->line('  ✓ Response timing');
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error during Level 1 test: {$e->getMessage()}");
            if ($trace) {
                $this->line("Stack trace: {$e->getTraceAsString()}");
            }
            return self::FAILURE;
        }
    }
} 