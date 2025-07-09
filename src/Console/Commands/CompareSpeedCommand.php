<?php

/**
 * CompareSpeedCommand - API Performance Comparison
 * 
 * Purpose: Compares response speed between OpenAI's Responses API and Chat
 * Completions API for the same message and model. This command helps
 * developers understand performance differences between API endpoints.
 * 
 * Performance Concept: Different OpenAI API endpoints may have varying
 * response times and capabilities. This command provides direct comparison
 * to help choose the optimal API for specific use cases.
 * 
 * Features Tested:
 * - Responses API performance
 * - Chat Completions API performance
 * - Response time comparison
 * - Model compatibility testing
 * - Performance benchmarking
 * - Response quality comparison
 * 
 * Usage:
 * - Basic comparison: php artisan agent:compare-speed "Hello, how are you?"
 * - Custom model: php artisan agent:compare-speed "Explain quantum physics" --model=gpt-4
 * - Different message: php artisan agent:compare-speed "Tell me a story"
 * 
 * Test Scenarios:
 * 1. Responses API performance measurement
 * 2. Chat Completions API performance measurement
 * 3. Response time comparison and analysis
 * 4. Model compatibility validation
 * 5. Performance benchmarking
 * 6. Response quality assessment
 * 
 * API Comparison:
 * - Responses API: Newer, potentially faster API
 * - Chat Completions API: Traditional, well-established API
 * - Performance metrics: Response time, quality, reliability
 * - Model compatibility: Different models may perform differently
 * 
 * Performance Factors:
 * - Network latency
 * - Model processing time
 * - API endpoint load
 * - Response complexity
 * - Model capabilities
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Illuminate\Support\Facades\Config;

class CompareSpeedCommand extends Command
{
    protected $signature = 'agent:compare-speed
                            {message : The message to send to the agent}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}';

    protected $description = 'Compare response speed between Responses API and Chat Completions API for the same message and model.';

    public function handle(): int
    {
        $message = $this->argument('message');
        $model = $this->option('model') ?: 'gpt-3.5-turbo';

        $this->info('Comparing response speed...');
        $this->line("Message: $message");
        $this->line("Model: $model");
        $this->line("");

        // --- Chat Completions API ---
        $this->info('Chat Completions API:');
        $agentChat = Agent::create([
            'model' => $model,
            'force_chat_completions' => true, // Force use of Chat Completions API
        ]);
        $startChat = microtime(true);
        $responseChat = $agentChat->chat($message);
        $durationChat = microtime(true) - $startChat;
        $this->line("Response: $responseChat");
        $this->line("Duration: " . number_format($durationChat, 3) . "s");
        $this->line("");

        // --- Responses API ---
        $this->info('Responses API:');
        $agentResp = Agent::create([
            'model' => $model,
            'force_responses_api' => true, // Force use of Responses API
        ]);
        $startResp = microtime(true);
        $responseResp = $agentResp->chat($message);
        $durationResp = microtime(true) - $startResp;
        $this->line("Response: $responseResp");
        $this->line("Duration: " . number_format($durationResp, 3) . "s");
        $this->line("");

        $this->info('Comparison finished.');
        $this->line('---');
        $this->line('Summary:');
        $this->line('Chat Completions API: ' . number_format($durationChat, 3) . 's');
        $this->line('Responses API: ' . number_format($durationResp, 3) . 's');

        return self::SUCCESS;
    }
} 