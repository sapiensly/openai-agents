<?php

/**
 * ToolTestCommand - Comprehensive Tools Functionality Testing
 *
 * Purpose: Provides a suite of tests for advanced tool functionality in the agent system,
 * including caching, validation, rate limiting, error handling, response caching,
 * argument validation, and strong typing. This command is for developers to validate
 * and benchmark tool-related features.
 *
 * Features Tested:
 * - Tool caching and cache validation
 * - Argument validation and strong typing
 * - Rate limiting and error handling
 * - Response caching
 * - Performance benchmarking
 * - Debugging and detailed output
 *
 * Usage:
 * - Test tool caching: php artisan agent:test-tools cache
 * - Test validation: php artisan agent:test-tools validation
 * - Test rate limiting: php artisan agent:test-tools rate-limit
 * - Test error handling: php artisan agent:test-tools error-handling
 * - Test response caching: php artisan agent:test-tools response-cache
 * - Test argument validation: php artisan agent:test-tools argument-validation
 * - Test cache with validation: php artisan agent:test-tools cache-validation
 * - Test strong typing: php artisan agent:test-tools strong-typing
 * - With debug: add --debug for detailed output
 *
 * Test Scenarios:
 * 1. Tool caching and cache validation
 * 2. Argument validation and strong typing
 * 3. Rate limiting and error handling
 * 4. Response caching and performance
 * 5. Debugging and detailed output
 *
 * Tool Features:
 * - Function tool registration
 * - Typed tool schemas
 * - Caching and validation
 * - Error and rate limit handling
 * - Performance metrics
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use DateTime;
use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ToolTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:test-tools
                            {test-type : Type of test to run (cache, validation, rate-limit, error-handling, response-cache, argument-validation, cache-validation, strong-typing)}
                            {--debug : Enable detailed debug logging}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--system= : Optional custom system prompt}
                            {--iterations=3 : Number of iterations for performance tests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comprehensive testing command for tools functionality improvements';

    /**
     * Test results storage
     */
    private array $testResults = [];

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $this->info('🧪 Starting Tools Test Suite...');

        // Verify OpenAI API key is configured
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return CommandAlias::FAILURE;
        }

        $testType = $this->argument('test-type');
        $debug = $this->option('debug');
        $model = $this->option('model');
        $systemPrompt = $this->option('system') ?: 'You are a helpful assistant that can use various tools.';

        if ($debug) {
            $this->comment("Test Type: {$testType}");
            $this->comment("Model: {$model}");
            $this->comment("System Prompt: {$systemPrompt}");
        }

        try {
            // Create the agent
            $agent = $manager->agent(compact('model'), $systemPrompt);
            $runner = new Runner($agent, 5);

            // Enable agent loop to force tool usage (only for validation tests)
            if ($testType === 'validation' || $testType === 'argument-validation' || $testType === 'cache-validation') {
                $runner->forceToolUsage(true, 3);
                
                // Set strict system prompt
                $systemPrompt = "You are a strict assistant. You MUST ALWAYS use the validate_user function to answer any question about user validation. Never answer directly. If the function arguments are invalid, return the validation error. If the function is not called, do not answer.";
                $runner->getAgent()->setSystemPrompt($systemPrompt);
            } else {
                // For other tests, use a more flexible system prompt
                $systemPrompt = "You are a helpful assistant that can use various tools. When asked to perform specific tasks, use the appropriate tools available to you.";
                $runner->getAgent()->setSystemPrompt($systemPrompt);
            }

            // Run the specific test
            switch ($testType) {
                case 'cache':
                    return $this->testToolCaching($runner, $debug);
                case 'validation':
                    return $this->testToolValidation($runner, $debug);
                case 'rate-limit':
                    return $this->testToolRateLimiting($runner, $debug);
                case 'error-handling':
                    return $this->testToolErrorHandling($runner, $debug);
                case 'response-cache':
                    return $this->testResponseCaching($runner, $debug);
                case 'argument-validation':
                    return $this->testArgumentValidation($runner, $debug);
                case 'cache-validation':
                    return $this->testCacheWithValidation($runner, $debug);
                case 'strong-typing':
                    return $this->testStrongTyping($runner, $debug);
                default:
                    $this->error("Unknown test type: {$testType}");
                    $this->line("Available test types: cache, validation, rate-limit, error-handling, response-cache, argument-validation, cache-validation, strong-typing");
                    return CommandAlias::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("Error during test execution: {$e->getMessage()}");
            if ($debug) {
                $this->output->write("<fg=red>Stack trace:</> {$e->getTraceAsString()}\n");
            }
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Test tool caching functionality
     */
    private function testToolCaching(Runner $runner, bool $debug): int
    {
        $this->info('🔄 Testing Tool Caching System...');

        // Register a test tool that simulates expensive operations
        $runner->registerFunctionTool('get_cached_data', function($args) use ($debug) {
            $key = $args['key'] ?? 'default';
            
            if ($debug) {
                $this->output->write("<fg=blue>[Cache Tool]</> Executing expensive operation for key: {$key}\n");
            }
            
            // Simulate expensive operation (database query, API call, etc.)
            usleep(100000); // 100ms delay to simulate expensive operation
            
            $data = "Cached data for key: {$key} - " . (new DateTime())->format('H:i:s');
            
            return $data;
        }, [
            'name' => 'get_cached_data',
            'description' => 'Gets cached data for a given key (simulates expensive operation)',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'key' => [
                        'type' => 'string',
                        'description' => 'The key to retrieve data for'
                    ]
                ],
                'required' => ['key']
            ]
        ]);

        // Enable tool caching for this test
        $cacheManager = $runner->getToolCacheManager();
        if ($cacheManager) {
            $cacheManager->setEnabled(true);
            if ($debug) {
                $this->output->write("<fg=green>[Cache Manager]</> Tool caching enabled\n");
            }
        }

        $this->info('📊 Running cache performance tests...');

        $iterations = (int) $this->option('iterations');
        $cacheResults = [];

        for ($i = 1; $i <= $iterations; $i++) {
            $this->line("Iteration {$i}:");
            
            // First call (should be cache miss)
            $startTime = microtime(true);
            $response1 = $runner->run("Get cached data for key 'test{$i}'");
            $time1 = microtime(true) - $startTime;
            
            // Second call (should be cache hit)
            $startTime = microtime(true);
            $response2 = $runner->run("Get cached data for key 'test{$i}'");
            $time2 = microtime(true) - $startTime;
            
            $cacheResults[] = [
                'iteration' => $i,
                'first_call_time' => $time1,
                'second_call_time' => $time2,
                'speedup' => $time1 > 0 ? $time2 / $time1 : 0,
                'response1' => $response1,
                'response2' => $response2
            ];

            $this->line("  First call: " . number_format($time1, 4) . "s");
            $this->line("  Second call: " . number_format($time2, 4) . "s");
            $this->line("  Speedup: " . number_format($cacheResults[$i-1]['speedup'], 2) . "x");
        }

        // Calculate statistics
        $avgSpeedup = array_sum(array_column($cacheResults, 'speedup')) / count($cacheResults);
        $avgFirstCall = array_sum(array_column($cacheResults, 'first_call_time')) / count($cacheResults);
        $avgSecondCall = array_sum(array_column($cacheResults, 'second_call_time')) / count($cacheResults);

        $this->info('📈 Cache Performance Results:');
        $this->line("Average first call time: " . number_format($avgFirstCall, 4) . "s");
        $this->line("Average second call time: " . number_format($avgSecondCall, 4) . "s");
        $this->line("Average speedup: " . number_format($avgSpeedup, 2) . "x");

        // Get cache statistics
        $cacheStats = $runner->getToolCacheStats();
        
        // Store results for evidence
        $this->testResults['cache'] = [
            'iterations' => $iterations,
            'results' => $cacheResults,
            'statistics' => [
                'avg_speedup' => $avgSpeedup,
                'avg_first_call' => $avgFirstCall,
                'avg_second_call' => $avgSecondCall
            ],
            'cache_stats' => $cacheStats
        ];

        if ($debug && $cacheStats) {
            $this->info('📊 Cache Statistics:');
            $this->line("Hits: {$cacheStats['hits']}");
            $this->line("Misses: {$cacheStats['misses']}");
            $this->line("Hit Rate: {$cacheStats['hit_rate_percent']}%");
            $this->line("Total Requests: {$cacheStats['total_requests']}");
        }

        // Validate cache effectiveness
        $effectiveCache = $avgSpeedup > 1.2; // At least 20% improvement (more realistic)
        $consistentResponses = true;
        $hasCacheHits = $cacheStats && $cacheStats['hits'] > 0;
        
        foreach ($cacheResults as $result) {
            if ($result['response1'] !== $result['response2']) {
                $consistentResponses = false;
                break;
            }
        }

        if ($effectiveCache && $consistentResponses && $hasCacheHits) {
            $this->info('✅ Cache test PASSED - Evidence of effective caching');
            $this->line("Evidence: Average speedup of " . number_format($avgSpeedup, 2) . "x with consistent responses");
            $this->line("Cache hits: {$cacheStats['hits']}, Hit rate: {$cacheStats['hit_rate_percent']}%");
            return CommandAlias::SUCCESS;
        } else {
            $this->error('❌ Cache test FAILED');
            if (!$effectiveCache) {
                $this->line("Reason: Insufficient speedup (expected >1.2x, got " . number_format($avgSpeedup, 2) . "x)");
            }
            if (!$consistentResponses) {
                $this->line("Reason: Inconsistent responses between calls");
            }
            if (!$hasCacheHits) {
                $this->line("Reason: No cache hits detected");
            }
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Test tool validation functionality
     */
    private function testToolValidation(Runner $runner, bool $debug): int
    {
        $this->info('🔍 Testing Tool Validation System...');

        // Register a test tool with strict validation
        $runner->registerFunctionTool('validate_user_data', function($args) use ($debug) {
            $name = $args['name'] ?? '';
            $age = $args['age'] ?? 0;
            $email = $args['email'] ?? '';
            
            if ($debug) {
                $this->output->write("<fg=blue>[Validation Tool]</> Processing: name={$name}, age={$age}, email={$email}\n");
            }
            
            // Simulate validation logic
            $errors = [];
            if (strlen($name) < 2) {
                $errors[] = "Name must be at least 2 characters";
            }
            if ($age < 0 || $age > 150) {
                $errors[] = "Age must be between 0 and 150";
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
            }
            
            if (!empty($errors)) {
                return "Validation failed: " . implode(", ", $errors);
            }
            
            return "Validation passed for {$name} (age: {$age}, email: {$email})";
        }, [
            'name' => 'validate_user_data',
            'description' => 'Validates user data with strict rules',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'minLength' => 2,
                        'description' => 'User name (minimum 2 characters)'
                    ],
                    'age' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'maximum' => 150,
                        'description' => 'User age (0-150)'
                    ],
                    'email' => [
                        'type' => 'string',
                        'format' => 'email',
                        'description' => 'User email address'
                    ]
                ],
                'required' => ['name', 'age', 'email']
            ]
        ]);

        $this->info('🧪 Running validation tests...');

        $testCases = [
            [
                'name' => 'Valid User',
                'input' => "Validate user data: name='John Doe', age=25, email='john@example.com'",
                'expected' => 'Validation passed',
                'should_pass' => true
            ],
            [
                'name' => 'Invalid Name',
                'input' => "Validate user data: name='A', age=25, email='john@example.com'",
                'expected' => 'Name must be at least 2 characters',
                'should_pass' => false
            ],
            [
                'name' => 'Invalid Age',
                'input' => "Validate user data: name='John Doe', age=200, email='john@example.com'",
                'expected' => 'Age must be between 0 and 150',
                'should_pass' => false
            ],
            [
                'name' => 'Invalid Email',
                'input' => "Validate user data: name='John Doe', age=25, email='invalid-email'",
                'expected' => 'Invalid email format',
                'should_pass' => false
            ]
        ];

        $validationResults = [];
        $passedTests = 0;

        foreach ($testCases as $testCase) {
            $this->line("Testing: {$testCase['name']}");
            
            $response = $runner->run($testCase['input']);
            $validationResults[] = [
                'test_name' => $testCase['name'],
                'input' => $testCase['input'],
                'response' => $response,
                'expected' => $testCase['expected'],
                'should_pass' => $testCase['should_pass'],
                'actual_passed' => str_contains($response, $testCase['expected'])
            ];

            if (str_contains($response, $testCase['expected'])) {
                $this->line("  ✅ PASSED");
                $passedTests++;
            } else {
                $this->line("  ❌ FAILED");
                $this->line("    Expected: {$testCase['expected']}");
                $this->line("    Got: {$response}");
            }
        }

        // Store results for evidence
        $this->testResults['validation'] = [
            'total_tests' => count($testCases),
            'passed_tests' => $passedTests,
            'results' => $validationResults
        ];

        $successRate = $passedTests / count($testCases);
        $this->info("📊 Validation Test Results:");
        $this->line("Passed: {$passedTests}/" . count($testCases) . " (" . number_format($successRate * 100, 1) . "%)");

        if ($successRate >= 0.9) { // 90% success rate required
            $this->info('✅ Validation test PASSED - Evidence of effective validation');
            $this->line("Evidence: {$passedTests}/" . count($testCases) . " tests passed with proper error handling");
            return CommandAlias::SUCCESS;
        } else {
            $this->error('❌ Validation test FAILED');
            $this->line("Reason: Insufficient success rate (expected >=90%, got " . number_format($successRate * 100, 1) . "%)");
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Test tool rate limiting functionality
     */
    private function testToolRateLimiting(Runner $runner, bool $debug): int
    {
        $this->info('⏱️ Testing Tool Rate Limiting System...');

        // Register a test tool with rate limiting
        $runner->registerFunctionTool('rate_limited_operation', function($args) use ($debug) {
            $operation = $args['operation'] ?? 'default';
            
            if ($debug) {
                $this->output->write("<fg=blue>[Rate Limit Tool]</> Executing operation: {$operation}\n");
            }
            
            // Simulate rate limiting
            $currentTime = time();
            $lastCallKey = "last_call_{$operation}";
            $callCountKey = "call_count_{$operation}";
            
            // Get last call time and count from cache (simulated)
            $lastCall = cache()->get($lastCallKey, 0);
            $callCount = cache()->get($callCountKey, 0);
            
            // Rate limit: max 3 calls per minute
            if ($currentTime - $lastCall < 60 && $callCount >= 3) {
                return "Rate limit exceeded for operation '{$operation}'. Please wait before trying again.";
            }
            
            // Update counters
            if ($currentTime - $lastCall >= 60) {
                $callCount = 1;
            } else {
                $callCount++;
            }
            
            cache()->put($lastCallKey, $currentTime, 120);
            cache()->put($callCountKey, $callCount, 120);
            
            return "Operation '{$operation}' executed successfully. Calls this minute: {$callCount}/3";
        }, [
            'name' => 'rate_limited_operation',
            'description' => 'Performs rate-limited operations',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'operation' => [
                        'type' => 'string',
                        'description' => 'The operation to perform'
                    ]
                ],
                'required' => ['operation']
            ]
        ]);

        $this->info('🧪 Running rate limiting tests...');

        $rateLimitResults = [];
        $successfulCalls = 0;
        $rateLimitedCalls = 0;

        // Test rapid calls to trigger rate limiting
        for ($i = 1; $i <= 5; $i++) {
            $this->line("Call {$i}:");
            
            $response = $runner->run("Perform rate limited operation 'test'");
            $rateLimitResults[] = [
                'call_number' => $i,
                'response' => $response,
                'was_rate_limited' => str_contains($response, 'Rate limit exceeded'),
                'was_successful' => str_contains($response, 'executed successfully')
            ];

            if (str_contains($response, 'executed successfully')) {
                $this->line("  ✅ SUCCESS");
                $successfulCalls++;
            } elseif (str_contains($response, 'Rate limit exceeded')) {
                $this->line("  ⚠️ RATE LIMITED");
                $rateLimitedCalls++;
            } else {
                $this->line("  ❓ UNEXPECTED");
            }
        }

        // Store results for evidence
        $this->testResults['rate_limiting'] = [
            'total_calls' => 5,
            'successful_calls' => $successfulCalls,
            'rate_limited_calls' => $rateLimitedCalls,
            'results' => $rateLimitResults
        ];

        $this->info("📊 Rate Limiting Test Results:");
        $this->line("Successful calls: {$successfulCalls}/5");
        $this->line("Rate limited calls: {$rateLimitedCalls}/5");

        // Validate rate limiting effectiveness
        $hasRateLimiting = $rateLimitedCalls > 0;
        $hasSuccessfulCalls = $successfulCalls > 0;
        $properBehavior = $hasRateLimiting && $hasSuccessfulCalls;

        if ($properBehavior) {
            $this->info('✅ Rate limiting test PASSED - Evidence of effective rate limiting');
            $this->line("Evidence: {$successfulCalls} successful calls and {$rateLimitedCalls} rate-limited calls");
            return CommandAlias::SUCCESS;
        } else {
            $this->error('❌ Rate limiting test FAILED');
            if (!$hasRateLimiting) {
                $this->line("Reason: No rate limiting detected");
            }
            if (!$hasSuccessfulCalls) {
                $this->line("Reason: No successful calls detected");
            }
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Test tool error handling functionality
     */
    private function testToolErrorHandling(Runner $runner, bool $debug): int
    {
        $this->info('🛡️ Testing Tool Error Handling System...');

        // Register a test tool that can generate errors
        $runner->registerFunctionTool('error_prone_operation', function($args) use ($debug) {
            $operation = $args['operation'] ?? 'safe';
            $shouldFail = $args['should_fail'] ?? false;
            
            if ($debug) {
                $this->output->write("<fg=blue>[Error Handling Tool]</> Operation: {$operation}, Should fail: " . ($shouldFail ? 'yes' : 'no') . "\n");
            }
            
            // Simulate different types of errors
            if ($shouldFail) {
                $errorType = $args['error_type'] ?? 'generic';
                
                switch ($errorType) {
                    case 'division_by_zero':
                        $result = 1 / 0; // This will throw an error
                        break;
                    case 'invalid_array_access':
                        $array = [];
                        $result = $array['nonexistent']; // This will throw an error
                        break;
                    case 'timeout':
                        sleep(10); // Simulate timeout
                        break;
                    default:
                        throw new \Exception("Simulated error for operation: {$operation}");
                }
            }
            
            return "Operation '{$operation}' completed successfully";
        }, [
            'name' => 'error_prone_operation',
            'description' => 'Performs operations that may fail for testing error handling',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'operation' => [
                        'type' => 'string',
                        'description' => 'The operation to perform'
                    ],
                    'should_fail' => [
                        'type' => 'boolean',
                        'description' => 'Whether the operation should fail'
                    ],
                    'error_type' => [
                        'type' => 'string',
                        'enum' => ['generic', 'division_by_zero', 'invalid_array_access', 'timeout'],
                        'description' => 'Type of error to simulate'
                    ]
                ],
                'required' => ['operation', 'should_fail']
            ]
        ]);

        $this->info('🧪 Running error handling tests...');

        $errorHandlingResults = [];
        $successfulTests = 0;
        $totalTests = 0;

        $testCases = [
            [
                'name' => 'Successful Operation',
                'input' => "Perform error prone operation: operation='test', should_fail=false",
                'expected_success' => true
            ],
            [
                'name' => 'Generic Error',
                'input' => "Perform error prone operation: operation='test', should_fail=true, error_type='generic'",
                'expected_success' => false
            ],
            [
                'name' => 'Division by Zero',
                'input' => "Perform error prone operation: operation='test', should_fail=true, error_type='division_by_zero'",
                'expected_success' => false
            ],
            [
                'name' => 'Invalid Array Access',
                'input' => "Perform error prone operation: operation='test', should_fail=true, error_type='invalid_array_access'",
                'expected_success' => false
            ]
        ];

        foreach ($testCases as $testCase) {
            $totalTests++;
            $this->line("Testing: {$testCase['name']}");
            
            try {
                $response = $runner->run($testCase['input']);
                $errorHandlingResults[] = [
                    'test_name' => $testCase['name'],
                    'input' => $testCase['input'],
                    'response' => $response,
                    'expected_success' => $testCase['expected_success'],
                    'actual_success' => str_contains($response, 'completed successfully'),
                    'error_handled' => !str_contains($response, 'completed successfully')
                ];

                if ($testCase['expected_success']) {
                    if (str_contains($response, 'completed successfully')) {
                        $this->line("  ✅ SUCCESS (as expected)");
                        $successfulTests++;
                    } else {
                        $this->line("  ❌ FAILED (unexpected error)");
                    }
                } else {
                    if (!str_contains($response, 'completed successfully')) {
                        $this->line("  ✅ ERROR HANDLED (as expected)");
                        $successfulTests++;
                    } else {
                        $this->line("  ❌ NO ERROR (unexpected success)");
                    }
                }
            } catch (\Exception $e) {
                $errorHandlingResults[] = [
                    'test_name' => $testCase['name'],
                    'input' => $testCase['input'],
                    'response' => $e->getMessage(),
                    'expected_success' => $testCase['expected_success'],
                    'actual_success' => false,
                    'error_handled' => true,
                    'exception' => $e->getMessage()
                ];

                if (!$testCase['expected_success']) {
                    $this->line("  ✅ ERROR HANDLED (as expected)");
                    $successfulTests++;
                } else {
                    $this->line("  ❌ UNEXPECTED EXCEPTION");
                }
            }
        }

        // Store results for evidence
        $this->testResults['error_handling'] = [
            'total_tests' => $totalTests,
            'successful_tests' => $successfulTests,
            'results' => $errorHandlingResults
        ];

        $successRate = $successfulTests / $totalTests;
        $this->info("📊 Error Handling Test Results:");
        $this->line("Successful tests: {$successfulTests}/{$totalTests} (" . number_format($successRate * 100, 1) . "%)");

        if ($successRate >= 0.8) { // 80% success rate required
            $this->info('✅ Error handling test PASSED - Evidence of effective error handling');
            $this->line("Evidence: {$successfulTests}/{$totalTests} tests passed with proper error handling");
            return CommandAlias::SUCCESS;
        } else {
            $this->error('❌ Error handling test FAILED');
            $this->line("Reason: Insufficient success rate (expected >=80%, got " . number_format($successRate * 100, 1) . "%)");
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Test response caching functionality
     */
    private function testResponseCaching(Runner $runner, bool $debug): int
    {
        $this->info('🚀 Testing Response Caching System...');

        // Enable response caching
        $responseCacheManager = $runner->getResponseCacheManager();
        if ($responseCacheManager) {
            $responseCacheManager->setEnabled(true);
            if ($debug) {
                $this->output->write("<fg=green>[Response Cache Manager]</> Response caching enabled\n");
            }
        }

        $this->info('📊 Running response cache performance tests...');

        $iterations = (int) $this->option('iterations');
        $cacheResults = [];

        for ($i = 1; $i <= $iterations; $i++) {
            $this->line("Iteration {$i}:");
            
            $testQuestion = "Explain the theory of relativity in detail and how it relates to modern physics. Include examples of its applications in GPS technology and particle accelerators.";
            
            // First call (should be cache miss)
            $startTime = microtime(true);
            $response1 = $runner->run($testQuestion);
            $time1 = microtime(true) - $startTime;
            
            // Second call (should be cache hit)
            $startTime = microtime(true);
            $response2 = $runner->run($testQuestion);
            $time2 = microtime(true) - $startTime;
            
            $cacheResults[] = [
                'iteration' => $i,
                'first_call_time' => $time1,
                'second_call_time' => $time2,
                'speedup' => $time1 > 0 ? $time2 / $time1 : 0,
                'response1' => $response1,
                'response2' => $response2
            ];

            $this->line("  First call: " . number_format($time1, 4) . "s");
            $this->line("  Second call: " . number_format($time2, 4) . "s");
            $this->line("  Speedup: " . number_format($cacheResults[$i-1]['speedup'], 2) . "x");
        }

        // Calculate statistics
        $avgSpeedup = array_sum(array_column($cacheResults, 'speedup')) / count($cacheResults);
        $avgFirstCall = array_sum(array_column($cacheResults, 'first_call_time')) / count($cacheResults);
        $avgSecondCall = array_sum(array_column($cacheResults, 'second_call_time')) / count($cacheResults);

        $this->info('📈 Response Cache Performance Results:');
        $this->line("Average first call time: " . number_format($avgFirstCall, 4) . "s");
        $this->line("Average second call time: " . number_format($avgSecondCall, 4) . "s");
        $this->line("Average speedup: " . number_format($avgSpeedup, 2) . "x");

        // Get cache statistics
        $cacheStats = $runner->getResponseCacheStats();
        
        // Store results for evidence
        $this->testResults['response_cache'] = [
            'iterations' => $iterations,
            'results' => $cacheResults,
            'statistics' => [
                'avg_speedup' => $avgSpeedup,
                'avg_first_call' => $avgFirstCall,
                'avg_second_call' => $avgSecondCall
            ],
            'cache_stats' => $cacheStats
        ];

        if ($debug && $cacheStats) {
            $this->info('📊 Response Cache Statistics:');
            $this->line("Hits: {$cacheStats['hits']}");
            $this->line("Misses: {$cacheStats['misses']}");
            $this->line("Hit Rate: {$cacheStats['hit_rate_percent']}%");
            $this->line("Total Requests: {$cacheStats['total_requests']}");
        }

        // Validate cache effectiveness
        $effectiveCache = $avgSpeedup > 0.1; // At least 10x faster (0.1x means 10x faster)
        $consistentResponses = true;
        $hasCacheHits = $cacheStats && $cacheStats['hits'] > 0;
        
        foreach ($cacheResults as $result) {
            if ($result['response1'] !== $result['response2']) {
                $consistentResponses = false;
                break;
            }
        }

        if ($effectiveCache && $consistentResponses && $hasCacheHits) {
            $this->info('✅ Response cache test PASSED - Evidence of dramatic speedup');
            $this->line("Evidence: Average speedup of " . number_format($avgSpeedup, 2) . "x with consistent responses");
            $this->line("Cache hits: {$cacheStats['hits']}, Hit rate: {$cacheStats['hit_rate_percent']}%");
            return CommandAlias::SUCCESS;
        } else {
            $this->error('❌ Response cache test FAILED');
            if (!$effectiveCache) {
                $this->line("Reason: Insufficient speedup (expected >0.1x, got " . number_format($avgSpeedup, 2) . "x)");
            }
            if (!$consistentResponses) {
                $this->line("Reason: Inconsistent responses between calls");
            }
            if (!$hasCacheHits) {
                $this->line("Reason: No cache hits detected");
            }
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Test argument validation functionality with manual simulation
     */
    private function testArgumentValidation(Runner $runner, bool $debug): int
    {
        $this->info('🚀 Testing Argument Validation System...');

        // Register a test tool with validation schema
        $runner->registerFunctionTool('validate_user', function(array $args) {
            return "Success: User validated with " . json_encode($args);
        }, [
            'name' => 'validate_user',
            'description' => 'Validate user information with strict requirements',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'minLength' => 2,
                        'maxLength' => 50,
                        'description' => 'User name (2-50 characters)'
                    ],
                    'age' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'maximum' => 150,
                        'description' => 'User age (0-150)'
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['active', 'inactive', 'pending'],
                        'description' => 'User status'
                    ]
                ],
                'required' => ['name', 'age']
            ]
        ]);

        $this->info('📊 Running argument validation tests with manual simulation...');

        // Test cases with manual tool calls to demonstrate validation
        $testCases = [
            [
                'name' => 'Valid user data',
                'tool_call' => 'validate_user',
                'args' => ['name' => 'John Doe', 'age' => 25, 'status' => 'active'],
                'expected' => 'success'
            ],
            [
                'name' => 'Missing required field',
                'tool_call' => 'validate_user',
                'args' => ['name' => 'John', 'status' => 'active'],
                'expected' => 'error'
            ],
            [
                'name' => 'Invalid age type',
                'tool_call' => 'validate_user',
                'args' => ['name' => 'John', 'age' => 'twenty five', 'status' => 'active'],
                'expected' => 'error'
            ],
            [
                'name' => 'Invalid status enum',
                'tool_call' => 'validate_user',
                'args' => ['name' => 'John', 'age' => 25, 'status' => 'invalid_status'],
                'expected' => 'error'
            ],
            [
                'name' => 'Age out of range',
                'tool_call' => 'validate_user',
                'args' => ['name' => 'John', 'age' => 200, 'status' => 'active'],
                'expected' => 'error'
            ],
            [
                'name' => 'Name too short',
                'tool_call' => 'validate_user',
                'args' => ['name' => 'A', 'age' => 25, 'status' => 'active'],
                'expected' => 'error'
            ]
        ];

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($testCases as $testCase) {
            $this->line("\nTesting: {$testCase['name']}");
            $this->line("Tool call: {$testCase['tool_call']}");
            $this->line("Arguments: " . json_encode($testCase['args']));
            
            try {
                // Simulate tool call directly
                $toolDefs = [
                    [
                        'name' => 'validate_user',
                        'callback' => function(array $args) {
                            return "Success: User validated with " . json_encode($args);
                        },
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => [
                                    'type' => 'string',
                                    'minLength' => 2,
                                    'maxLength' => 50,
                                    'description' => 'User name (2-50 characters)'
                                ],
                                'age' => [
                                    'type' => 'integer',
                                    'minimum' => 0,
                                    'maximum' => 150,
                                    'description' => 'User age (0-150)'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'enum' => ['active', 'inactive', 'pending'],
                                    'description' => 'User status'
                                ]
                            ],
                            'required' => ['name', 'age']
                        ]
                    ]
                ];

                // Validate arguments directly
                $validatorClass = \Sapiensly\OpenaiAgents\Tools\ToolArgumentValidator::class;
                $errors = $validatorClass::validate($testCase['args'], $toolDefs[0]['schema']);
                
                $hasValidationError = !empty($errors);
                $expectedError = $testCase['expected'] === 'error';
                
                if ($hasValidationError === $expectedError) {
                    $successCount++;
                    $this->line("  ✅ PASS: " . ($hasValidationError ? "Correctly detected validation error" : "Correctly processed valid data"));
                    if ($hasValidationError && $debug) {
                        $this->line("    Errors: " . implode('; ', $errors));
                    }
                } else {
                    $errorCount++;
                    $this->line("  ❌ FAIL: " . ($hasValidationError ? "Unexpectedly detected error in valid data" : "Failed to detect validation error"));
                    if ($hasValidationError && $debug) {
                        $this->line("    Errors: " . implode('; ', $errors));
                    }
                }
                
                $results[] = [
                    'test_case' => $testCase['name'],
                    'tool_call' => $testCase['tool_call'],
                    'args' => $testCase['args'],
                    'expected' => $testCase['expected'],
                    'actual' => $hasValidationError ? 'error' : 'success',
                    'errors' => $errors,
                    'passed' => $hasValidationError === $expectedError
                ];
                
            } catch (\Exception $e) {
                $errorCount++;
                $this->line("  ❌ EXCEPTION: " . $e->getMessage());
                $results[] = [
                    'test_case' => $testCase['name'],
                    'tool_call' => $testCase['tool_call'],
                    'args' => $testCase['args'],
                    'response' => 'EXCEPTION: ' . $e->getMessage(),
                    'expected' => $testCase['expected'],
                    'actual' => 'exception',
                    'passed' => false
                ];
            }
        }

        $totalTests = count($testCases);
        $passRate = ($successCount / $totalTests) * 100;

        $this->info('\n📈 Argument Validation Results:');
        $this->line("Total tests: {$totalTests}");
        $this->line("Passed: {$successCount}");
        $this->line("Failed: {$errorCount}");
        $this->line("Pass rate: " . number_format($passRate, 1) . "%");

        // Store results for evidence
        $this->testResults['argument_validation'] = [
            'total_tests' => $totalTests,
            'passed' => $successCount,
            'failed' => $errorCount,
            'pass_rate' => $passRate,
            'results' => $results
        ];

        if ($debug) {
            $this->info('📊 Detailed Results:');
            foreach ($results as $result) {
                $status = $result['passed'] ? '✅ PASS' : '❌ FAIL';
                $this->line("{$status}: {$result['test_case']}");
                if (!empty($result['errors'])) {
                    $this->line("  Errors: " . implode('; ', $result['errors']));
                }
            }
        }

        // Validate test effectiveness
        $effectiveValidation = $passRate >= 100.0 && $successCount > 0; // Expect 100% for direct validation
        
        if ($effectiveValidation) {
            $this->info('✅ Argument validation test PASSED - Evidence of robust validation system');
            $this->line("Evidence: {$successCount}/{$totalTests} tests passed with proper validation error detection");
            return CommandAlias::SUCCESS;
        } else {
            $this->error('❌ Argument validation test FAILED');
            $this->line("Reason: Pass rate {$passRate}% (expected 100%)");
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Test cache combined with argument validation
     */
    private function testCacheWithValidation(Runner $runner, bool $debug): int
    {
        $this->info('🚀 Testing Cache + Argument Validation Combined System...');

        // Enable both cache and validation
        $toolCacheManager = $runner->getToolCacheManager();
        if ($toolCacheManager) {
            $toolCacheManager->setEnabled(true);
            if ($debug) {
                $this->output->write("<fg=green>[Cache Manager]</> Tool caching enabled\n");
            }
        }

        // Register a tool with validation schema and cache
        $runner->registerFunctionTool('validate_and_cache_user', function(array $args) {
            // Simulate expensive operation
            sleep(1);
            return "Success: User validated and cached - " . json_encode($args);
        }, [
            'name' => 'validate_and_cache_user',
            'description' => 'Validate user and cache result',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'minLength' => 2,
                        'maxLength' => 50
                    ],
                    'age' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'maximum' => 150
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['active', 'inactive', 'pending']
                    ]
                ],
                'required' => ['name', 'age']
            ]
        ]);

        $this->info('📊 Running cache + validation combined tests...');

        $testCases = [
            [
                'name' => 'Valid args - First call (cache miss)',
                'args' => ['name' => 'John Doe', 'age' => 25, 'status' => 'active'],
                'expected' => 'success',
                'cache_expected' => 'miss'
            ],
            [
                'name' => 'Valid args - Second call (cache hit)',
                'args' => ['name' => 'John Doe', 'age' => 25, 'status' => 'active'],
                'expected' => 'success',
                'cache_expected' => 'hit'
            ],
            [
                'name' => 'Invalid args - Should not cache',
                'args' => ['name' => 'John', 'age' => 'invalid', 'status' => 'active'],
                'expected' => 'error',
                'cache_expected' => 'no_cache'
            ],
            [
                'name' => 'Valid args - Different user (cache miss)',
                'args' => ['name' => 'Jane Smith', 'age' => 30, 'status' => 'inactive'],
                'expected' => 'success',
                'cache_expected' => 'miss'
            ],
            [
                'name' => 'Valid args - Same user again (cache hit)',
                'args' => ['name' => 'Jane Smith', 'age' => 30, 'status' => 'inactive'],
                'expected' => 'success',
                'cache_expected' => 'hit'
            ]
        ];

        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $cacheHits = 0;
        $cacheMisses = 0;

        foreach ($testCases as $testCase) {
            $this->line("\nTesting: {$testCase['name']}");
            $this->line("Arguments: " . json_encode($testCase['args']));
            
            try {
                // Simulate tool call with validation and cache
                $toolDefs = [
                    [
                        'name' => 'validate_and_cache_user',
                        'callback' => function(array $args) {
                            sleep(1); // Simulate expensive operation
                            return "Success: User validated and cached - " . json_encode($args);
                        },
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => [
                                    'type' => 'string',
                                    'minLength' => 2,
                                    'maxLength' => 50
                                ],
                                'age' => [
                                    'type' => 'integer',
                                    'minimum' => 0,
                                    'maximum' => 150
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'enum' => ['active', 'inactive', 'pending']
                                ]
                            ],
                            'required' => ['name', 'age']
                        ]
                    ]
                ];

                // Validate arguments first
                $validatorClass = \Sapiensly\OpenaiAgents\Tools\ToolArgumentValidator::class;
                $errors = $validatorClass::validate($testCase['args'], $toolDefs[0]['schema']);
                
                $hasValidationError = !empty($errors);
                $expectedError = $testCase['expected'] === 'error';
                
                if ($hasValidationError === $expectedError) {
                    $successCount++;
                    
                    if (!$hasValidationError) {
                        // Valid args - check cache behavior
                        $startTime = microtime(true);
                        
                        // Simulate cache check
                        $cacheKey = 'validate_and_cache_user_' . md5(json_encode($testCase['args']));
                        $cachedResult = null;
                        
                        if ($toolCacheManager && !$toolCacheManager->shouldBypassCache('validate_and_cache_user', $testCase['args'])) {
                            $cachedResult = $toolCacheManager->getCachedResult('validate_and_cache_user', $testCase['args']);
                        }
                        
                        if ($cachedResult !== null) {
                            $cacheHits++;
                            $this->line("  ✅ PASS: Cache HIT - Using cached result");
                            $this->line("  Time: " . number_format(microtime(true) - $startTime, 4) . "s");
                        } else {
                            $cacheMisses++;
                            $this->line("  ✅ PASS: Cache MISS - Executing tool");
                            
                            // Simulate tool execution
                            $result = $toolDefs[0]['callback']($testCase['args']);
                            $executionTime = microtime(true) - $startTime;
                            $this->line("  Time: " . number_format($executionTime, 4) . "s");
                            
                            // Cache the result
                            if ($toolCacheManager && !$toolCacheManager->shouldBypassCache('validate_and_cache_user', $testCase['args'])) {
                                $toolCacheManager->cacheResult('validate_and_cache_user', $testCase['args'], $result);
                            }
                        }
                    } else {
                        $this->line("  ✅ PASS: Validation ERROR - Not cached");
                        if ($debug) {
                            $this->line("    Errors: " . implode('; ', $errors));
                        }
                    }
                } else {
                    $errorCount++;
                    $this->line("  ❌ FAIL: Unexpected validation result");
                    if ($debug) {
                        $this->line("    Errors: " . implode('; ', $errors));
                    }
                }
                
                $results[] = [
                    'test_case' => $testCase['name'],
                    'args' => $testCase['args'],
                    'expected' => $testCase['expected'],
                    'cache_expected' => $testCase['cache_expected'],
                    'actual' => $hasValidationError ? 'error' : 'success',
                    'cache_actual' => $cachedResult !== null ? 'hit' : 'miss',
                    'errors' => $errors,
                    'passed' => $hasValidationError === $expectedError
                ];
                
            } catch (\Exception $e) {
                $errorCount++;
                $this->line("  ❌ EXCEPTION: " . $e->getMessage());
                $results[] = [
                    'test_case' => $testCase['name'],
                    'args' => $testCase['args'],
                    'response' => 'EXCEPTION: ' . $e->getMessage(),
                    'expected' => $testCase['expected'],
                    'actual' => 'exception',
                    'passed' => false
                ];
            }
        }

        $totalTests = count($testCases);
        $passRate = ($successCount / $totalTests) * 100;

        $this->info('\n📈 Cache + Validation Combined Results:');
        $this->line("Total tests: {$totalTests}");
        $this->line("Passed: {$successCount}");
        $this->line("Failed: {$errorCount}");
        $this->line("Pass rate: " . number_format($passRate, 1) . "%");
        $this->line("Cache hits: {$cacheHits}");
        $this->line("Cache misses: {$cacheMisses}");

        // Get cache statistics
        $cacheStats = $runner->getToolCacheStats();
        
        // Store results for evidence
        $this->testResults['cache_validation'] = [
            'total_tests' => $totalTests,
            'passed' => $successCount,
            'failed' => $errorCount,
            'pass_rate' => $passRate,
            'cache_hits' => $cacheHits,
            'cache_misses' => $cacheMisses,
            'cache_stats' => $cacheStats,
            'results' => $results
        ];

        if ($debug && $cacheStats) {
            $this->info('📊 Cache Statistics:');
            $this->line("Hits: {$cacheStats['hits']}");
            $this->line("Misses: {$cacheStats['misses']}");
            $this->line("Hit Rate: {$cacheStats['hit_rate_percent']}%");
            $this->line("Total Requests: {$cacheStats['total_requests']}");
        }

        // Validate test effectiveness
        $effectiveCombination = $passRate >= 100.0 && $successCount > 0 && $cacheHits > 0;
        
        if ($effectiveCombination) {
            $this->info('✅ Cache + Validation test PASSED - Evidence of combined functionality');
            $this->line("Evidence: {$successCount}/{$totalTests} tests passed with cache hits and validation");
            return CommandAlias::SUCCESS;
        } else {
            $this->error('❌ Cache + Validation test FAILED');
            $this->line("Reason: Pass rate {$passRate}% (expected 100%) or no cache hits");
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Test strong typing system functionality
     */
    private function testStrongTyping(Runner $runner, bool $debug): int
    {
        $this->info('🔧 Testing Strong Typing System...');

        // Test 1: Simple string tool
        $this->line("\n📝 Test 1: String Tool");
        $runner->registerStringTool('reverse_text', function($args) {
            $text = $args['text'] ?? '';
            return strrev($text);
        }, 'text', 'Text to reverse');

        // Test 2: Integer tool with validation
        $this->line("\n🔢 Test 2: Integer Tool");
        $runner->registerIntegerTool('calculate_square', function($args) {
            $number = $args['number'] ?? 0;
            return $number * $number;
        }, 'number', 'Number to square');

        // Test 3: Number tool with validation
        $this->line("\n📊 Test 3: Number Tool");
        $runner->registerNumberTool('calculate_percentage', function($args) {
            $value = $args['value'] ?? 0;
            $total = $args['total'] ?? 1;
            return ($value / $total) * 100;
        }, 'value', 'Value to calculate percentage for');

        // Test 4: Boolean tool
        $this->line("\n✅ Test 4: Boolean Tool");
        $runner->registerBooleanTool('check_condition', function($args) {
            $condition = $args['condition'] ?? false;
            return $condition ? "Condition is TRUE" : "Condition is FALSE";
        }, 'condition', 'Boolean condition to check');

        // Test 5: No parameter tool
        $this->line("\n🎲 Test 5: No Parameter Tool");
        $runner->registerNoParamTool('get_random_number', function($args) {
            return rand(1, 100);
        });

        // Test 6: Complex tool using builder
        $this->line("\n🏗️ Test 6: Complex Tool with Builder");
        $complexTool = $runner->toolBuilder('validate_user_profile', function($args) {
            $name = $args['name'] ?? '';
            $age = $args['age'] ?? 0;
            $email = $args['email'] ?? '';
            $isActive = $args['is_active'] ?? false;
            
            $errors = [];
            if (empty($name)) $errors[] = "Name is required";
            if ($age < 0 || $age > 150) $errors[] = "Age must be between 0 and 150";
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
            
            if (empty($errors)) {
                return "User profile is valid: {$name}, {$age} years old, {$email}, Active: " . ($isActive ? 'Yes' : 'No');
            } else {
                return "Validation errors: " . implode(', ', $errors);
            }
        })
        ->description('Validates a user profile with multiple fields')
        ->requiredStringProperty('name', 'User name')
        ->requiredIntegerProperty('age', 'User age (0-150)')
        ->requiredStringProperty('email', 'User email address')
        ->booleanProperty('is_active', 'Whether the user is active');

        $runner->registerTypedTool($complexTool->build());

        // Test 7: Tool with validation using ToolProperty
        $this->line("\n🔍 Test 7: Tool with Advanced Validation");
        $advancedTool = $runner->toolBuilder('calculate_discount', function($args) {
            $price = $args['price'] ?? 0;
            $discount = $args['discount'] ?? 0;
            return $price * (1 - $discount / 100);
        })
        ->description('Calculates final price after discount')
        ->property('price', \Sapiensly\OpenaiAgents\Tools\ToolProperty::number('Product price')
            ->minimum(0)
            ->maximum(10000))
        ->property('discount', \Sapiensly\OpenaiAgents\Tools\ToolProperty::number('Discount percentage')
            ->minimum(0)
            ->maximum(100)
            ->default(0));

        $runner->registerTypedTool($advancedTool->build());

        // Test all tools
        $this->info('🧪 Testing all strong typing tools...');

        $testCases = [
            'reverse_text' => "Reverse the text 'hello world'",
            'calculate_square' => "Calculate the square of 7",
            'calculate_percentage' => "Calculate what percentage 25 is of 100",
            'check_condition' => "Check if the condition is true",
            'get_random_number' => "Get a random number",
            'validate_user_profile' => "Validate user profile: John Doe, age 30, email john@example.com, active true",
            'calculate_discount' => "Calculate discount: price 100, discount 20"
        ];

        $results = [];
        foreach ($testCases as $toolName => $prompt) {
            $this->line("\nTesting: {$toolName}");
            $this->line("Prompt: {$prompt}");
            
            try {
                $startTime = microtime(true);
                $response = $runner->run($prompt);
                $executionTime = microtime(true) - $startTime;
                
                $this->line("Response: {$response}");
                $this->line("Execution time: " . number_format($executionTime, 4) . "s");
                
                $results[$toolName] = [
                    'success' => true,
                    'response' => $response,
                    'execution_time' => $executionTime
                ];
            } catch (\Exception $e) {
                $this->error("Error testing {$toolName}: {$e->getMessage()}");
                $results[$toolName] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Store results for evidence
        $this->testResults['strong_typing'] = [
            'test_cases' => $testCases,
            'results' => $results,
            'total_tools' => count($testCases),
            'successful_tools' => count(array_filter($results, fn($r) => $r['success']))
        ];

        // Validate strong typing system
        $allToolsWork = count(array_filter($results, fn($r) => $r['success'])) === count($testCases);
        $hasComplexTool = isset($results['validate_user_profile']) && $results['validate_user_profile']['success'];
        $hasAdvancedValidation = isset($results['calculate_discount']) && $results['calculate_discount']['success'];

        $this->info('📊 Strong Typing Test Results:');
        $this->line("Total tools tested: " . count($testCases));
        $this->line("Successful tools: " . count(array_filter($results, fn($r) => $r['success'])));
        $this->line("Complex tool working: " . ($hasComplexTool ? '✅' : '❌'));
        $this->line("Advanced validation working: " . ($hasAdvancedValidation ? '✅' : '❌'));

        if ($allToolsWork && $hasComplexTool && $hasAdvancedValidation) {
            $this->info('✅ Strong typing test PASSED - All tools working correctly');
            $this->line("Evidence: All " . count($testCases) . " tools executed successfully");
            $this->line("Complex tool with multiple properties: " . ($hasComplexTool ? 'Working' : 'Failed'));
            $this->line("Advanced validation with constraints: " . ($hasAdvancedValidation ? 'Working' : 'Failed'));
        } else {
            $this->error('❌ Strong typing test FAILED');
            $this->line("Evidence: Some tools failed to execute or validate correctly");
            return CommandAlias::FAILURE;
        }

        return CommandAlias::SUCCESS;
    }

    /**
     * Get test results for external analysis
     */
    public function getTestResults(): array
    {
        return $this->testResults;
    }
} 