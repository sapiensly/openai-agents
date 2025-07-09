<?php

/**
 * TestStreaming - Real-time Response Streaming Testing
 * 
 * Purpose: Demonstrates and tests streaming capabilities with real-time output
 * and performance comparison between normal and streaming modes. This command
 * validates the streaming functionality for improved user experience.
 * 
 * Streaming Concept: Provides real-time, character-by-character output of AI
 * responses, allowing users to see responses as they're generated rather than
 * waiting for the complete response.
 * 
 * Features Tested:
 * - Real-time streaming output
 * - Performance comparison (normal vs streaming)
 * - Configurable streaming delays
 * - Timeout and length limits
 * - Tool usage with streaming
 * - Chunk counting and statistics
 * - Quick test mode for fast validation
 * 
 * Usage:
 * - Both modes: php artisan agent:test-streaming --mode=both
 * - Streaming only: php artisan agent:test-streaming --mode=streaming
 * - Normal only: php artisan agent:test-streaming --mode=normal
 * - Custom message: php artisan agent:test-streaming --message="Tell me a story"
 * - Quick test: php artisan agent:test-streaming --quick
 * - Custom delay: php artisan agent:test-streaming --delay=0.05
 * - Multiple turns: php artisan agent:test-streaming --turns=5
 * - Timeout: php artisan agent:test-streaming --timeout=30
 * - Length limit: php artisan agent:test-streaming --max-length=200
 * 
 * Test Scenarios:
 * 1. Normal mode (no streaming) performance
 * 2. Streaming mode with real-time output
 * 3. Performance comparison between modes
 * 4. Tool usage with streaming
 * 5. Timeout and length limit handling
 * 6. Chunk counting and statistics
 * 
 * Streaming Benefits:
 * - Improved user experience
 * - Real-time feedback
 * - Better perceived performance
 * - Progressive content display
 * - Reduced waiting time
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;
use Sapiensly\OpenaiAgents\Tools\ToolDefinition;
use Sapiensly\OpenaiAgents\Tools\ToolProperty;
use Sapiensly\OpenaiAgents\Tools\ToolSchema;

class TestStreaming extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:test-streaming 
                            {--mode=both : Test mode: normal, streaming, or both}
                            {--message=Tell me a short story about a robot : Initial message}
                            {--turns=3 : Number of conversation turns}
                            {--delay=0.1 : Delay between chunks (seconds)}
                            {--timeout=60 : Maximum time per turn in seconds}
                            {--max-length=500 : Maximum response length in characters}
                            {--quick : Quick test mode (1 turn, short prompts)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrate streaming capabilities with real-time output and performance comparison';

    public function handle(): int
    {
        $mode = $this->option('mode');
        $message = $this->option('message');
        $turns = (int) $this->option('turns');
        $delay = (float) $this->option('delay');
        $timeout = (int) $this->option('timeout');
        $maxLength = (int) $this->option('max-length');
        $quick = $this->option('quick');

        // Quick mode overrides
        if ($quick) {
            $turns = 1;
            $message = 'Tell me a brief story about a robot';
            $maxLength = 200;
            $timeout = 30;
        }

        $this->info('🚀 Streaming Test - Demonstrating Real-time AI Responses');
        if ($quick) {
            $this->info('⚡ Quick Mode: 1 turn, short response, 30s timeout');
        }
        $this->newLine();

        // Create agent with tools for more interesting streaming
        $manager = app(AgentManager::class);
        $agent = $manager->agent(null, 'You are a creative storyteller and helpful assistant. Always provide detailed, engaging responses that showcase your capabilities.');

        // Register some tools to demonstrate tool streaming
        $runner = new Runner($agent);
        $this->registerDemoTools($runner);

        if (in_array($mode, ['normal', 'both'])) {
            $this->testNormalMode($runner, $message, $turns, $timeout, $maxLength);
        }

        if (in_array($mode, ['streaming', 'both'])) {
            $this->testStreamingMode($runner, $message, $turns, $delay, $timeout, $maxLength);
        }

        if ($mode === 'both') {
            $this->comparePerformance($runner, $message);
        }

        $this->newLine();
        $this->info('✅ Streaming test completed!');
        $this->info('💡 Use --mode=streaming to see only streaming demo');
        $this->info('💡 Use --mode=normal to see only normal mode');
        $this->info('💡 Use --delay=0.05 for faster streaming or --delay=0.2 for slower');
        $this->info('💡 Use --quick for a fast test (1 turn, 30s timeout)');
        $this->info('💡 Use --timeout=30 to limit turn duration');
        $this->info('💡 Use --max-length=200 to limit response length');

        return self::SUCCESS;
    }

    private function testNormalMode(Runner $runner, string $message, int $turns, int $timeout, int $maxLength): void
    {
        $this->info('📝 Testing NORMAL Mode (No Streaming)');
        $this->info('=' . str_repeat('=', 50));
        
        $startTime = microtime(true);
        $totalTokens = 0;

        for ($i = 1; $i <= $turns; $i++) {
            $this->info("Turn {$i}:");
            $this->info("🤖 User: {$message}");
            
            $turnStart = microtime(true);
            
            // Set timeout for this turn
            $response = $this->runWithTimeout($runner, $message, $timeout);
            $turnTime = microtime(true) - $turnStart;
            
            // Truncate response if too long
            if (strlen($response) > $maxLength) {
                $response = substr($response, 0, $maxLength) . '... [truncated]';
            }
            
            $this->info("🤖 Assistant: {$response}");
            $this->info("⏱️  Turn time: " . number_format($turnTime, 3) . "s");
            $this->newLine();
            
            // Update message for next turn with shorter prompt
            $message = "Continue briefly.";
        }

        $totalTime = microtime(true) - $startTime;
        $this->info("📊 Normal Mode Summary:");
        $this->info("   Total time: " . number_format($totalTime, 3) . "s");
        $this->info("   Average per turn: " . number_format($totalTime / $turns, 3) . "s");
        $this->newLine();
    }

    private function testStreamingMode(Runner $runner, string $message, int $turns, float $delay, int $timeout, int $maxLength): void
    {
        $this->info('🌊 Testing STREAMING Mode (Real-time Output)');
        $this->info('=' . str_repeat('=', 50));
        
        $startTime = microtime(true);
        $totalChunks = 0;

        for ($i = 1; $i <= $turns; $i++) {
            $this->info("Turn {$i}:");
            $this->info("🤖 User: {$message}");
            $this->info("🤖 Assistant: ");
            
            $turnStart = microtime(true);
            $chunks = 0;
            $response = '';
            
            // Use streaming with timeout and length limit
            $streamStart = microtime(true);
            foreach ($runner->runStreamed($message) as $chunk) {
                echo $chunk;
                $response .= $chunk;
                $chunks++;
                $totalChunks++;
                
                // Check timeout
                if (microtime(true) - $streamStart > $timeout) {
                    $this->warn("⚠️  Timeout reached ({$timeout}s)");
                    break;
                }
                
                // Check length limit
                if (strlen($response) > $maxLength) {
                    echo '... [truncated]';
                    break;
                }
                
                // Add delay to make streaming visible
                if ($delay > 0) {
                    usleep((int) ($delay * 1000000));
                }
            }
            
            $turnTime = microtime(true) - $turnStart;
            $this->newLine();
            $this->info("⏱️  Turn time: " . number_format($turnTime, 3) . "s");
            $this->info("📦 Chunks received: {$chunks}");
            $this->newLine();
            
            // Update message for next turn with shorter prompt
            $message = "Continue briefly.";
        }

        $totalTime = microtime(true) - $startTime;
        $this->info("📊 Streaming Mode Summary:");
        $this->info("   Total time: " . number_format($totalTime, 3) . "s");
        $this->info("   Average per turn: " . number_format($totalTime / $turns, 3) . "s");
        $this->info("   Total chunks: {$totalChunks}");
        $this->newLine();
    }

    private function comparePerformance(Runner $runner, string $message): void
    {
        $this->info('📈 Performance Comparison');
        $this->info('=' . str_repeat('=', 50));
        
        // Test normal mode
        $normalStart = microtime(true);
        $normalResponse = $runner->run($message);
        $normalTime = microtime(true) - $normalStart;
        
        // Test streaming mode
        $streamingStart = microtime(true);
        $streamingChunks = [];
        foreach ($runner->runStreamed($message) as $chunk) {
            $streamingChunks[] = $chunk;
        }
        $streamingTime = microtime(true) - $streamingStart;
        
        $this->info("Normal Mode:");
        $this->info("   Time: " . number_format($normalTime, 3) . "s");
        $this->info("   Response length: " . strlen($normalResponse) . " chars");
        
        $this->info("Streaming Mode:");
        $this->info("   Time: " . number_format($streamingTime, 3) . "s");
        $this->info("   Response length: " . strlen(implode('', $streamingChunks)) . " chars");
        $this->info("   Chunks: " . count($streamingChunks));
        
        $timeDiff = $normalTime - $streamingTime;
        $this->info("Difference: " . number_format($timeDiff, 3) . "s (" . ($timeDiff > 0 ? 'slower' : 'faster') . ")");
        $this->newLine();
    }

    private function registerDemoTools(Runner $runner): void
    {
        // Tool that returns data in chunks to demonstrate tool streaming
        $runner->registerTool('get_weather_data', function (array $params) {
            $location = $params['location'] ?? 'New York';
            $data = [
                'location' => $location,
                'temperature' => rand(15, 30),
                'humidity' => rand(40, 80),
                'conditions' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
                'forecast' => [
                    'today' => 'Partly cloudy with a chance of rain',
                    'tomorrow' => 'Sunny and warm',
                    'weekend' => 'Clear skies expected'
                ]
            ];
            
            return json_encode($data, JSON_PRETTY_PRINT);
        }, new ToolSchema([
            new ToolProperty('location', 'string', 'City name for weather data', false)
        ]));

        // Tool that simulates processing time
        $runner->registerTool('process_data', function (array $params) {
            $data = $params['data'] ?? 'sample data';
            $iterations = $params['iterations'] ?? 5;
            
            $result = "Processing {$data} with {$iterations} iterations:\n";
            for ($i = 1; $i <= $iterations; $i++) {
                $result .= "Step {$i}: Processing...\n";
                usleep(100000); // 0.1 second delay
            }
            $result .= "✅ Processing complete!";
            
            return $result;
        }, new ToolSchema([
            new ToolProperty('data', 'string', 'Data to process', false),
            new ToolProperty('iterations', 'integer', 'Number of processing steps', false)
        ]));
    }

    private function runWithTimeout(Runner $runner, string $message, int $timeout): string
    {
        $startTime = microtime(true);
        $response = '';
        
        try {
            // For normal mode, we'll use a simple timeout check
            $response = $runner->run($message);
            
            // Check if we exceeded timeout
            if (microtime(true) - $startTime > $timeout) {
                $this->warn("⚠️  Timeout reached ({$timeout}s)");
                return $response . ' [timeout reached]';
            }
            
            return $response;
        } catch (\Exception $e) {
            $this->error("❌ Error during execution: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }
} 