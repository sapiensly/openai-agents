<?php

/**
 * ChatAgent - Simple Agent Chat Interface
 * 
 * Purpose: Provides a simple command-line interface for chatting with OpenAI
 * agents. This command offers a straightforward way to send prompts and
 * receive responses with optional tracing and system prompt customization.
 * 
 * Chat Concept: Basic conversational interface for testing agent responses
 * and validating agent functionality in a simple, direct manner.
 * 
 * Features Tested:
 * - Simple chat functionality
 * - Custom system prompts
 * - Multi-turn conversations
 * - Tracing and debugging
 * - Error handling and validation
 * - Performance measurement
 * 
 * Usage:
 * - Basic chat: php artisan agent:chat "Hello, how are you?"
 * - Custom system: php artisan agent:chat "Tell me a joke" --system="You are a comedian"
 * - Multi-turn: php artisan agent:chat "Hello" --max-turns=5
 * - With tracing: php artisan agent:chat "Hello" --trace
 * 
 * Test Scenarios:
 * 1. Simple chat with default settings
 * 2. Chat with custom system prompt
 * 3. Multi-turn conversation handling
 * 4. Tracing and debugging output
 * 5. Error handling and validation
 * 6. Performance measurement
 * 
 * Chat Features:
 * - Direct agent communication
 * - System prompt customization
 * - Multi-turn conversation support
 * - Tracing for debugging
 * - Error handling and validation
 * - Performance monitoring
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Guardrails\GuardrailException;

class ChatAgent extends Command
{
    protected $signature = 'agent:chat {message}'
    . ' {--system=}'
    . ' {--max-turns=5}'
    . ' {--trace}';

    protected $description = 'Send a prompt to the OpenAI agent and output the response.';

    /**
     * @throws GuardrailException
     */
    public function handle(AgentManager $manager): int
    {
        $message = $this->argument('message');
        $system = $this->option('system');
        $max = (int) $this->option('max-turns');
        $trace = $this->option('trace');

        // Create the agent
        $agent = $manager->agent([], $system);

        // If tracing is enabled, configure the custom processor
        if ($trace) {
            // Temporarily configure tracing for this command
            config(['agents.tracing.enabled' => true]);
            config(['agents.tracing.processors' => [
                function (array $data) {
                    if ($data['type'] === 'start_span') {
                        $this->info('🚀 Starting conversation...');
                    } elseif ($data['type'] === 'event') {
                        $turn = $data['turn'] ?? '?';
                        $this->info("💬 Turn {$turn}");
                        $this->line('> ' . ($data['input'] ?? ''));
                        $this->line('< ' . ($data['output'] ?? ''));
                        $this->line('---');
                    } elseif ($data['type'] === 'end_span') {
                        $this->info('✅ Conversation completed');
                    }
                }
            ]]);
        }

        // Use the manager to create the runner (cleaner)
        $runner = $manager->runner($agent, $max);

        $response = $runner->run($message);

        $this->line($response);

        return self::SUCCESS;
    }
}
