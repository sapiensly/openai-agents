<?php

/**
 * WeatherAndTimeQuestionCommand - Weather and Time Tools Example
 *
 * Purpose: Demonstrates the use of agent tools for answering weather and time
 * questions, including strong typing, caching, and input validation. This command
 * shows step-by-step tool registration, usage, and debugging for weather and time queries.
 *
 * Features Tested:
 * - Weather and time question answering
 * - Tool registration with strong typing
 * - Caching and cache management
 * - Input validation and error handling
 * - Step-by-step process demonstration
 * - Debugging and detailed output
 *
 * Usage:
 * - Basic: php artisan agent:weather-time "What is the weather in Madrid?"
 * - Custom city: php artisan agent:weather-time "Weather?" --city=London
 * - Custom timezone: php artisan agent:weather-time "Time?" --timezone=America/New_York
 * - Step-by-step: php artisan agent:weather-time "Weather and time?" --step-by-step
 * - Debug: php artisan agent:weather-time "Weather?" --debug
 * - No cache: php artisan agent:weather-time "Weather?" --no-cache
 * - No validation: php artisan agent:weather-time "Weather?" --no-validation
 *
 * Test Scenarios:
 * 1. Weather and time tool registration and usage
 * 2. Caching and cache management
 * 3. Input validation and error handling
 * 4. Step-by-step process demonstration
 * 5. Debugging and detailed output
 *
 * Tool Features:
 * - get_weather: Returns weather information for a city
 * - get_current_time: Returns current time for a timezone
 *
 * Error Handling:
 * - API key validation
 * - Input validation errors
 * - Cache and tool errors
 *
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;
use Symfony\Component\Console\Command\Command as CommandAlias;

class WeatherAndTimeQuestionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:weather-time
                            {question? : The weather or time question to ask}
                            {--city=Madrid : Default city for weather queries}
                            {--timezone=Europe/Madrid : Default timezone for time queries}
                            {--max-turns=5 : Maximum number of conversation turns}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--system= : Optional custom system prompt}
                            {--debug : Enable detailed debug logging}
                            {--step-by-step : Show each step of the process}
                            {--no-cache : Disable caching for testing}
                            {--no-validation : Disable input validation for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrates weather and time tools with caching and validation - shows each step of the process';

    /**
     * Test results storage
     */
    private array $testResults = [];

    /**
     * Tools used during execution
     */
    private array $toolsUsed = [];

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $this->info('🌤️ Starting Weather and Time Question Runner...');
        $this->line('This example demonstrates each step of the process with caching and validation.');

        // Verify OpenAI API key is configured
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            $this->line('You can get an API key from: https://platform.openai.com/api-keys');
            return CommandAlias::FAILURE;
        }

        $this->comment("Using API Key: " . substr($apiKey, 0, 10) . "...");

        // Get command options
        $question = $this->argument('question') ?: 'What is the weather in Madrid and what time is it?';
        $defaultCity = $this->option('city');
        $defaultTimezone = $this->option('timezone');
        $maxTurns = (int) $this->option('max-turns');
        $model = $this->option('model');
        $debug = $this->option('debug');
        $stepByStep = $this->option('step-by-step');
        $noCache = $this->option('no-cache');
        $noValidation = $this->option('no-validation');

        // Configure system prompt
        $systemPrompt = $this->option('system') ?:
            "You are a helpful assistant that can answer questions about weather and time. " .
            "You have access to tools for getting weather information and current time. " .
            "Always use the appropriate tools when asked about weather or time. " .
            "For weather queries, provide temperature, conditions, and humidity. " .
            "For time queries, provide the current time in the specified timezone. " .
            "Always respond in a helpful and conversational manner.";

        if ($debug) {
            $this->comment("System prompt: {$systemPrompt}");
            $this->comment("Model: {$model}");
            $this->comment("Max turns: {$maxTurns}");
            $this->comment("Default city: {$defaultCity}");
            $this->comment("Default timezone: {$defaultTimezone}");
            $this->comment("Cache enabled: " . (!$noCache ? 'Yes' : 'No'));
            $this->comment("Validation enabled: " . (!$noValidation ? 'Yes' : 'No'));
        }

        try {
            // STEP 1: Create the agent
            if ($stepByStep) {
                $this->info('📋 STEP 1: Creating agent...');
            }
            
            $agent = $manager->agent(compact('model'), $systemPrompt);
            $runner = new Runner($agent, $maxTurns);

            if ($stepByStep) {
                $this->line('✅ Agent created successfully');
            }

            // STEP 2: Configure caching
            if ($stepByStep) {
                $this->info('📋 STEP 2: Configuring caching system...');
            }

            $cacheManager = $runner->getToolCacheManager();
            if ($cacheManager && !$noCache) {
                $cacheManager->setEnabled(true);
                
                if ($debug) {
                    $this->output->write("<fg=green>[Cache Manager]</> Tool caching enabled\n");
                }
                
                if ($stepByStep) {
                    $this->line('✅ Caching enabled');
                }
            } else {
                if ($stepByStep) {
                    $this->line('⚠️  Caching disabled');
                }
            }

            // STEP 3: Register weather tool with strong typing
            if ($stepByStep) {
                $this->info('📋 STEP 3: Registering weather tool with strong typing...');
            }

            $weatherTool = $runner->toolBuilder('get_weather', function($args) use ($debug, $stepByStep, $defaultCity) {
                // Track tool usage
                if (!in_array('get_weather', $this->toolsUsed)) {
                    $this->toolsUsed[] = 'get_weather';
                }
                
                $city = $args['city'] ?? $defaultCity;
                $unit = $args['unit'] ?? 'celsius';
                
                if ($stepByStep) {
                    $this->output->write("<fg=blue>[Weather Tool]</> Processing request for city: {$city}\n");
                }
                
                if ($debug) {
                    $this->output->write("<fg=blue>[Weather Tool]</> Getting weather for {$city} in {$unit}\n");
                }
                
                // Input validation
                if (empty($city) || strlen($city) < 2) {
                    return "Error: City name must be at least 2 characters long";
                }
                
                if (!in_array($unit, ['celsius', 'fahrenheit'])) {
                    return "Error: Unit must be 'celsius' or 'fahrenheit'";
                }
                
                // Simulate weather API call with cache simulation
                $temperature = rand(10, 30);
                $conditions = ['sunny', 'cloudy', 'rainy', 'partly cloudy', 'stormy'][array_rand([0, 1, 2, 3, 4])];
                $humidity = rand(30, 90);
                
                $tempSymbol = $unit === 'celsius' ? '°C' : '°F';
                
                $weatherInfo = "Weather in {$city}: {$temperature}{$tempSymbol}, {$conditions}, humidity: {$humidity}%";
                
                if ($stepByStep) {
                    $this->output->write("<fg=green>[Weather Tool]</> ✅ Weather data retrieved: {$weatherInfo}\n");
                }
                
                return $weatherInfo;
            })
            ->description('Gets weather information for a specific city')
            ->requiredStringProperty('city', 'City name (minimum 2 characters)')
            ->property('unit', \Sapiensly\OpenaiAgents\Tools\ToolProperty::string('Temperature unit')
                ->enum(['celsius', 'fahrenheit'])
                ->default('celsius'));

            $runner->registerTypedTool($weatherTool->build());

            if ($stepByStep) {
                $this->line('✅ Weather tool registered with validation');
            }

            // STEP 4: Register time tool with strong typing
            if ($stepByStep) {
                $this->info('📋 STEP 4: Registering time tool with strong typing...');
            }

            $timeTool = $runner->toolBuilder('get_current_time', function($args) use ($debug, $stepByStep, $defaultTimezone) {
                // Track tool usage
                if (!in_array('get_current_time', $this->toolsUsed)) {
                    $this->toolsUsed[] = 'get_current_time';
                }
                
                $timezone = $args['timezone'] ?? $defaultTimezone;
                $format = $args['format'] ?? 'full';
                
                if ($stepByStep) {
                    $this->output->write("<fg=blue>[Time Tool]</> Processing request for timezone: {$timezone}\n");
                }
                
                if ($debug) {
                    $this->output->write("<fg=blue>[Time Tool]</> Getting current time for {$timezone}\n");
                }
                
                // Input validation
                if (empty($timezone)) {
                    return "Error: Timezone is required";
                }
                
                try {
                    $tz = new DateTimeZone($timezone);
                } catch (\Exception $e) {
                    return "Error: Invalid timezone '{$timezone}'";
                }
                
                if (!in_array($format, ['full', 'short', 'time-only'])) {
                    return "Error: Format must be 'full', 'short', or 'time-only'";
                }
                
                // Get current time in specified timezone
                $now = new DateTime('now', $tz);
                
                $timeInfo = match($format) {
                    'full' => $now->format('l, F j, Y \a\t g:i A T'),
                    'short' => $now->format('M j, Y g:i A'),
                    'time-only' => $now->format('g:i A T'),
                    default => $now->format('Y-m-d H:i:s T')
                };
                
                if ($stepByStep) {
                    $this->output->write("<fg=green>[Time Tool]</> ✅ Time data retrieved: {$timeInfo}\n");
                }
                
                return $timeInfo;
            })
            ->description('Gets current time in a specific timezone')
            ->requiredStringProperty('timezone', 'Timezone (e.g., Europe/Madrid, America/New_York)')
            ->property('format', \Sapiensly\OpenaiAgents\Tools\ToolProperty::string('Time format to return')
                ->enum(['full', 'short', 'time-only'])
                ->default('full'));

            $runner->registerTypedTool($timeTool->build());

            if ($stepByStep) {
                $this->line('✅ Time tool registered with validation');
            }

            // STEP 5: Register combined weather and time tool with strong typing
            if ($stepByStep) {
                $this->info('📋 STEP 5: Registering combined weather and time tool with strong typing...');
            }

            $combinedTool = $runner->toolBuilder('get_weather_and_time', function($args) use ($debug, $stepByStep, $defaultCity, $defaultTimezone) {
                // Track tool usage
                if (!in_array('get_weather_and_time', $this->toolsUsed)) {
                    $this->toolsUsed[] = 'get_weather_and_time';
                }
                
                $city = $args['city'] ?? $defaultCity;
                $timezone = $args['timezone'] ?? $defaultTimezone;
                $unit = $args['unit'] ?? 'celsius';
                
                if ($stepByStep) {
                    $this->output->write("<fg=blue>[Combined Tool]</> Processing combined request for {$city} in {$timezone}\n");
                }
                
                // Validate inputs
                if (empty($city) || strlen($city) < 2) {
                    return "Error: City name must be at least 2 characters long";
                }
                
                try {
                    $tz = new DateTimeZone($timezone);
                } catch (\Exception $e) {
                    return "Error: Invalid timezone '{$timezone}'";
                }
                
                // Simulate weather data
                $temperature = rand(10, 30);
                $conditions = ['sunny', 'cloudy', 'rainy', 'partly cloudy'][array_rand([0, 1, 2, 3])];
                $humidity = rand(30, 90);
                
                // Get current time
                $now = new DateTime('now', $tz);
                $timeInfo = $now->format('g:i A T');
                
                $tempSymbol = $unit === 'celsius' ? '°C' : '°F';
                
                $combinedInfo = "Weather in {$city}: {$temperature}{$tempSymbol}, {$conditions}, humidity: {$humidity}% | Current time: {$timeInfo}";
                
                if ($stepByStep) {
                    $this->output->write("<fg=green>[Combined Tool]</> ✅ Combined data retrieved: {$combinedInfo}\n");
                }
                
                return $combinedInfo;
            })
            ->description('Gets both weather and current time for a city')
            ->requiredStringProperty('city', 'City name (minimum 2 characters)')
            ->stringProperty('timezone', 'Timezone for time calculation')
            ->property('unit', \Sapiensly\OpenaiAgents\Tools\ToolProperty::string('Temperature unit')
                ->enum(['celsius', 'fahrenheit'])
                ->default('celsius'));

            $runner->registerTypedTool($combinedTool->build());

            if ($stepByStep) {
                $this->line('✅ Combined tool registered');
            }

            // STEP 6: Run the conversation
            if ($stepByStep) {
                $this->info('📋 STEP 6: Running conversation with tools...');
            }

            $this->output->write("<fg=yellow>Running conversation...</>\n\n");
            $this->output->write("<fg=green>Question:</> {$question}\n\n");

            // Show progress bar during processing
            $progressBar = null;
            if (!$debug && !$stepByStep) {
                $progressBar = $this->output->createProgressBar();
                $progressBar->start();
            }

            // Run the conversation
            $startTime = microtime(true);
            $response = $runner->run($question);
            $executionTime = microtime(true) - $startTime;

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
                return CommandAlias::FAILURE;
            }

            $this->output->write("<fg=green>Response:</> {$response}\n\n");

            // STEP 7: Show execution statistics
            if ($stepByStep) {
                $this->info('📋 STEP 7: Execution statistics...');
            }

            $this->info('📊 Execution Statistics:');
            $this->line("Execution time: " . number_format($executionTime, 4) . "s");
            $this->line("Model used: {$model}");
            $this->line("Max turns: {$maxTurns}");
            $this->line("Tools used: " . (empty($this->toolsUsed) ? 'none' : implode(', ', $this->toolsUsed)));

            // Get cache statistics if available
            if ($cacheManager && !$noCache) {
                $cacheStats = $runner->getToolCacheStats();
                if ($cacheStats) {
                    $this->line("Cache hits: " . ($cacheStats['hits'] ?? 0));
                    $this->line("Cache misses: " . ($cacheStats['misses'] ?? 0));
                    $this->line("Cache hit rate: " . number_format(($cacheStats['hit_rate'] ?? 0) * 100, 1) . "%");
                }
            }

            // STEP 8: Demonstrate caching with repeated calls
            if ($stepByStep && !$noCache) {
                $this->info('📋 STEP 8: Demonstrating caching with repeated calls...');
                
                $this->line('Testing cache with repeated weather query...');
                
                // First call
                $startTime1 = microtime(true);
                $response1 = $runner->run("What's the weather in Barcelona?");
                $time1 = microtime(true) - $startTime1;
                
                // Second call (should be cached)
                $startTime2 = microtime(true);
                $response2 = $runner->run("What's the weather in Barcelona?");
                $time2 = microtime(true) - $startTime2;
                
                $this->line("First call time: " . number_format($time1, 4) . "s");
                $this->line("Second call time: " . number_format($time2, 4) . "s");
                $this->line("Speedup: " . number_format($time1 > 0 ? $time2 / $time1 : 0, 2) . "x");
                
                // Get updated cache stats
                $updatedStats = $runner->getToolCacheStats();
                if ($updatedStats) {
                    $this->line("Updated cache hits: " . ($updatedStats['hits'] ?? 0));
                    $this->line("Updated cache misses: " . ($updatedStats['misses'] ?? 0));
                }
            }

            // STEP 9: Demonstrate validation
            if ($stepByStep && !$noValidation) {
                $this->info('📋 STEP 9: Demonstrating input validation...');
                
                $this->line('Testing validation with invalid inputs...');
                
                // Test invalid city name
                $this->line('Testing invalid city name (too short):');
                $invalidResponse = $runner->run("What's the weather in A?");
                $this->line("Response: {$invalidResponse}");
                
                // Test invalid timezone
                $this->line('Testing invalid timezone:');
                $invalidTzResponse = $runner->run("What time is it in Invalid/Timezone?");
                $this->line("Response: {$invalidTzResponse}");
            }

            $this->info('✅ Weather and Time example completed successfully!');
            
            // Store test results
            $this->testResults = [
                'execution_time' => $executionTime,
                'model' => $model,
                'max_turns' => $maxTurns,
                'tools_used' => $this->toolsUsed,
                'cache_enabled' => !$noCache,
                'validation_enabled' => !$noValidation,
                'response' => $response,
                'cache_stats' => $runner->getToolCacheStats()
            ];
            
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

            return CommandAlias::FAILURE;
        }
    }

    /**
     * Get test results
     */
    public function getTestResults(): array
    {
        return $this->testResults;
    }
} 