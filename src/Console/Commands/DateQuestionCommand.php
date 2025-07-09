<?php

/**
 * DateQuestionCommand - Date and Time Question Example
 *
 * Purpose: Demonstrates the use of the Runner and agent tools for answering
 * date and time-related questions. This command shows how to register tools
 * for date retrieval and how to handle both tool-based and direct agent responses.
 *
 * Features Tested:
 * - Date and time question answering
 * - Tool registration for date retrieval
 * - System prompt customization
 * - Debugging and error handling
 * - Multi-turn conversation support
 *
 * Usage:
 * - Basic: php artisan agent:date-question "What day is today?"
 * - No tools: php artisan agent:date-question "What day is today?" --no-tools
 * - Custom system: php artisan agent:date-question "What day is today?" --system="You are a date assistant"
 * - Debug: php artisan agent:date-question "What day is today?" --debug
 *
 * Test Scenarios:
 * 1. Date question with tool-based answer
 * 2. Date question with direct agent answer (no tools)
 * 3. System prompt customization
 * 4. Debugging and error handling
 * 5. Multi-turn conversation
 *
 * Tool Features:
 * - get_current_date: Returns the current date and time
 *
 * Error Handling:
 * - API key validation
 * - Network and quota errors
 * - Tool schema and function errors
 *
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use DateTime;
use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;
use Symfony\Component\Console\Command\Command as CommandAlias;

class DateQuestionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:date-question
                            {question? : The question to ask about the date}
                            {--max-turns=3 : Maximum number of conversation turns}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--system= : Optional custom system prompt}
                            {--debug : Enable detailed debug logging}
                            {--no-tools : Disable tools and just use basic chat}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrates the Runner with a date question example';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $this->info('Starting date question runner example...');

        // Verify OpenAI API key is configured
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            $this->line('You can get an API key from: https://platform.openai.com/api-keys');
            return CommandAlias::FAILURE;
        }

        $this->comment("Using API Key: " . substr($apiKey, 0, 10) . "...");

        // Get command options with the default value
        $question = $this->argument('question') ?: 'What day is today?';
        $maxTurns = (int) $this->option('max-turns');
        $model = $this->option('model');
        $debug = $this->option('debug');
        $noTools = $this->option('no-tools');

        // Configure system prompt
        if ($noTools) {
            $systemPrompt = $this->option('system') ?:
                "You are a helpful assistant that answers questions about dates and time. ".
                "Today's date is " . (new DateTime())->format('l, F j, Y') . ". ".
                "Always respond in a helpful and conversational manner.";
        } else {
            $systemPrompt = $this->option('system') ?:
                "You are a helpful assistant that can answer questions about dates and time. ".
                "When you need to get the current date and time, you can call the 'get_current_date' function. ".
                "Always respond in a helpful and conversational manner.";
        }

        if ($debug) {
            $this->comment("System prompt: {$systemPrompt}");
            $this->comment("Model: {$model}");
            $this->comment("Max turns: {$maxTurns}");
            $this->comment("No tools: " . ($noTools ? 'Yes' : 'No'));
        }

        try {
            // Create the agent using Laravel's dependency injection
            $agent = $manager->agent(compact('model'), $systemPrompt);

            // Create the runner with logging enabled
            $runner = new Runner($agent, $maxTurns);

            if (!$noTools) {
                // Show spinning indicator during execution
                $this->output->write("<fg=yellow>Setting up tools...</>\n");

                // Register the tool using strong typing system
                $runner->registerNoParamTool('get_current_date', function($args) use ($debug) {
                    if ($debug) {
                        $this->output->write("<fg=blue>[Date Tool]</> Getting current date\n");
                    }
                    $date = new DateTime();
                    return $date->format('Y-m-d H:i:s l, d F Y');
                });

                if ($debug) {
                    $this->comment("Registered tools: get_current_date");
                }
            }

            $this->output->write("<fg=yellow>Running conversation...</>\n\n");
            $this->output->write("<fg=green>Question:</> {$question}\n\n");

            // Show progress bar during processing
            $progressBar = null;
            if (!$debug) {
                $progressBar = $this->output->createProgressBar();
                $progressBar->start();
            }

            // First, let's test a simple direct agent call to debug
            if ($debug) {
                $this->comment("Testing direct agent call...");
                $directResponse = $agent->chat("Hello, can you say 'test'?");
                $this->comment("Direct agent response: {$directResponse}");

                if (empty($directResponse)) {
                    $this->error("Agent is returning empty responses. Check your OpenAI API configuration.");
                    return CommandAlias::FAILURE;
                }
            }

            // Run the conversation with the question
            $response = $runner->run($question);

            if ($progressBar) {
                $progressBar->finish();
                $this->output->write("\n\n");
            }

            if (empty($response)) {
                $this->error("Received empty response from agent. Possible issues:");
                $this->line("1. Invalid OpenAI API key");
                $this->line("2. Network connectivity problems");
                $this->line("3. OpenAI API rate limiting");
                $this->line("4. Model '{$model}' is not available");
                $this->line("5. Tools configuration issue");
                $this->line("\nTry running with --no-tools flag to test without function calling.");
                return CommandAlias::FAILURE;
            }

            $this->output->write("<fg=green>Response:</> {$response}\n\n");
            $this->info('Example completed successfully!');
            return CommandAlias::SUCCESS;

        } catch (\Exception $e) {
            if (isset($progressBar) && $progressBar) {
                $progressBar->finish();
                $this->output->write("\n\n");
            }

            $this->error("Error executing agent: {$e->getMessage()}");

            if ($debug) {
                $this->output->write("<fg=red>Stack trace:</> {$e->getTraceAsString()}\n");
            } else {
                $this->line("Run with --debug flag for detailed error information.");
            }

            // Provide helpful suggestions based on common error patterns
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'api_key')) {
                $this->line("\n<fg=yellow>Suggestion:</> Check your OpenAI API key configuration.");
            } elseif (str_contains($errorMessage, 'curl') || str_contains($errorMessage, 'network')) {
                $this->line("\n<fg=yellow>Suggestion:</> Check your network connection.");
            } elseif (str_contains($errorMessage, 'rate') || str_contains($errorMessage, 'quota')) {
                $this->line("\n<fg=yellow>Suggestion:</> You may have exceeded your OpenAI API quota or rate limit.");
            } elseif (str_contains($errorMessage, 'schema') || str_contains($errorMessage, 'function')) {
                $this->line("\n<fg=yellow>Suggestion:</> Try running with --no-tools flag to test without function calling.");
            }

            return CommandAlias::FAILURE;
        }
    }
}
