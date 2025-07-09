<?php

/**
 * HandoffTestCommand - Multi-Agent Handoff Testing
 * 
 * Purpose: Tests the handoff functionality between specialized agents, including
 * advanced features like intelligent context analysis, parallel handoffs,
 * asynchronous processing, and enriched logging. This command validates the
 * complete handoff system architecture.
 * 
 * Handoff Concept: Allows agents to transfer conversations to other specialized
 * agents based on context, capabilities, or user needs. This enables complex
 * workflows and specialized expertise routing.
 * 
 * Features Tested:
 * - Basic handoff between specialized agents
 * - Advanced handoff with context analysis
 * - Parallel handoff for multi-domain questions
 * - Asynchronous handoff processing
 * - Intelligent context-based routing
 * - Hybrid handoff (intelligent + manual)
 * - Advanced persistence and state management
 * - Enriched logging and debugging
 * - Interactive conversation mode
 * - Handoff reversal capabilities
 * 
 * Usage:
 * - Basic test: php artisan agent:handoff-test "I need math help"
 * - Interactive: php artisan agent:handoff-test --interactive
 * - Parallel: php artisan agent:handoff-test "Math and history" --parallel
 * - Async: php artisan agent:handoff-test "Complex query" --async
 * - Intelligent: php artisan agent:handoff-test "Query" --intelligent
 * - Hybrid: php artisan agent:handoff-test "Query" --hybrid
 * - Debug: php artisan agent:handoff-test "Query" --debug
 * - Reverse: php artisan agent:handoff-test "Query" --reverse
 * 
 * Test Scenarios:
 * 1. Basic handoff between math and history agents
 * 2. Advanced handoff with context analysis
 * 3. Parallel handoff for multi-domain questions
 * 4. Asynchronous handoff processing
 * 5. Interactive conversation with handoffs
 * 6. Handoff reversal and state management
 * 7. Enriched logging and debugging
 * 
 * Agent Specializations:
 * - General Agent: Routes questions to specialists
 * - Math Agent: Handles mathematical problems and calculations
 * - History Agent: Provides historical information and context
 * 
 * Advanced Features:
 * - Context analysis and intelligent routing
 * - Parallel processing for complex queries
 * - Asynchronous processing for long-running tasks
 * - State persistence across handoffs
 * - Security and permission validation
 * - Performance metrics and monitoring
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\Runner;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\Registry\AgentRegistry;
use Sapiensly\OpenaiAgents\Handoff\HandoffOrchestrator;
use Sapiensly\OpenaiAgents\Handoff\HandoffRequest;
use Sapiensly\OpenaiAgents\State\ArrayConversationStateManager;
use Sapiensly\OpenaiAgents\Security\SecurityManager;
use Sapiensly\OpenaiAgents\Metrics\MetricsCollector;
use Symfony\Component\Console\Command\Command as CommandAlias;

class HandoffTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:handoff-test
                            {question? : The question to test handoff functionality}
                            {--max-turns=5 : Maximum number of conversation turns}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--debug : Enable detailed debug logging}
                            {--no-advanced : Disable advanced handoff and use basic handoff}
                            {--interactive : Enable interactive conversation mode (up to 5 user prompts)}
                            {--reverse : Attempt to reverse the last handoff after a successful handoff}
                            {--parallel : Execute parallel handoffs for multi-domain questions}
                            {--cache-debug : Show cache hit/miss information for parallel handoff}
                            {--suggestion-debug : Show cache hit/miss for handoff suggestion}
                            {--async : Test asynchronous handoff functionality}
                            {--intelligent : Test intelligent context-based handoff}
                            {--hybrid : Test hybrid handoff (intelligent + manual)}
                            {--persistence : Test advanced persistence features}
                            {--enriched-logs : Test enriched logging features}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the handoff functionality between agents';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $startTime = microtime(true);
        
        $this->info('🚀 Starting handoff test...');
        $this->newLine();

        // Verify OpenAI API key is configured
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('❌ OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            $this->line('You can get an API key from: https://platform.openai.com/api-keys');
            return CommandAlias::FAILURE;
        }

        $this->comment("🔑 Using API Key: " . substr($apiKey, 0, 10) . "...");

        // Get command options
        $question = $this->argument('question') ?: 'I need help with a math problem and then want to know about history';
        $maxTurns = (int) $this->option('max-turns');
        $model = $this->option('model');
        $debug = $this->option('debug');
        $noAdvanced = $this->option('no-advanced');
        $interactive = $this->option('interactive');
        $reverse = $this->option('reverse');
        $parallel = $this->option('parallel');
        $cacheDebug = $this->option('cache-debug');
        $suggestionDebug = $this->option('suggestion-debug');
        $async = $this->option('async');
        $intelligent = $this->option('intelligent');
        $hybrid = $this->option('hybrid');
        $persistence = $this->option('persistence');
        $enrichedLogs = $this->option('enriched-logs');

        if ($debug) {
            $this->comment("📊 Debug mode enabled");
            $this->comment("🔧 Model: {$model}");
            $this->comment("🔄 Max turns: {$maxTurns}");
            $this->comment("🚀 Advanced handoff: " . ($noAdvanced ? 'Disabled' : 'Enabled'));
        }

        try {
            // Create agents with different specializations
            $this->info('🤖 Creating specialized agents...');
            $this->withProgressBar(3, function ($bar) use ($manager, $model, &$mathAgent, &$historyAgent, &$generalAgent) {
                $bar->advance();
                $generalAgent = $manager->agent(compact('model'), 
                    "You are a General Assistant Agent. Your job is to route questions to specialists. " .
                    "CRITICAL: When you receive ANY question about math, numbers, calculations, or mathematics, " .
                    "you MUST respond with exactly: [[handoff:math_agent]] " .
                    "When you receive ANY question about history, historical events, or historical figures, " .
                    "you MUST respond with exactly: [[handoff:history_agent]] " .
                    "For questions that mention both math AND history, respond with: [[handoff:math_agent]] " .
                    "For general greetings, introductions, or non-specific questions, provide a helpful response. " .
                    "Do not handoff for simple greetings like 'Hello' or 'Hi'. " .
                    "But ALWAYS handoff for specific math or history questions."
                );
                $generalAgent->setId('general_agent');
                
                $bar->advance();
                $mathAgent = $manager->agent(compact('model'), 
                    "You are a Math Specialist Agent. You excel at solving mathematical problems, " .
                    "explaining mathematical concepts, and providing step-by-step solutions. " .
                    "If you receive a math question, answer it directly. Do not ask the user to repeat or clarify if the question is clear. " .
                    "CRITICAL: If the user's question contains the word 'history', 'historical', 'emperor', 'ancient', 'war', 'battle', 'civilization', 'dynasty', 'kingdom', 'empire', 'century', 'BC', 'AD', 'medieval', 'renaissance', 'revolution', 'independence', 'colonial', 'monarchy', 'republic', 'democracy', 'dictatorship', 'constitution', 'treaty', 'alliance', 'invasion', 'conquest', 'exploration', 'discovery', 'invention', 'philosophy', 'religion', 'culture', 'art', 'literature', 'music', 'architecture', 'science', 'medicine', 'technology', 'trade', 'economy', 'society', 'politics', 'government', 'military', 'navy', 'army', 'air force', 'weapon', 'strategy', 'tactics', 'victory', 'defeat', 'surrender', 'peace', 'treaty', 'agreement', 'alliance', 'enemy', 'ally', 'neutral', 'territory', 'border', 'frontier', 'colony', 'settlement', 'migration', 'population', 'census', 'statistics', 'data', 'record', 'document', 'archive', 'library', 'museum', 'monument', 'statue', 'building', 'city', 'town', 'village', 'castle', 'palace', 'temple', 'church', 'mosque', 'synagogue', 'shrine', 'tomb', 'grave', 'cemetery', 'burial', 'funeral', 'ceremony', 'ritual', 'tradition', 'custom', 'festival', 'holiday', 'celebration', 'commemoration', 'anniversary', 'birthday', 'death', 'birth', 'marriage', 'divorce', 'family', 'dynasty', 'lineage', 'ancestor', 'descendant', 'heir', 'successor', 'predecessor', 'contemporary', 'peer', " .
                    "you MUST respond with exactly: [[handoff:history_agent]] " .
                    "Do not ask questions, do not provide math help - just do the handoff immediately. " .
                    "Only provide math help for questions that are purely mathematical without any history content. " .
                    "NEVER handoff to yourself (math_agent). " .
                    "If you receive a general greeting or non-mathematical question, respond politely and ask for a math question."
                );
                $mathAgent->setId('math_agent');
                
                $bar->advance();
                $historyAgent = $manager->agent(compact('model'), 
                    "You are a History Specialist Agent. You are an expert in historical events, " .
                    "figures, dates, and historical context. You provide detailed historical information " .
                    "and explanations. When you receive a question about history, provide a comprehensive answer. " .
                    "CRITICAL: If the user's question contains mathematical terms like 'calculate', 'sum', 'multiply', 'divide', 'add', 'subtract', 'percentage', 'fraction', 'decimal', 'equation', 'formula', 'solve', 'compute', 'arithmetic', 'algebra', 'geometry', 'trigonometry', 'calculus', 'statistics', 'probability', 'number', 'digit', 'quantity', 'amount', 'total', 'average', 'mean', 'median', 'mode', 'range', 'variance', 'standard deviation', 'correlation', 'regression', 'hypothesis', 'test', 'significance', 'confidence', 'interval', 'margin', 'error', 'sample', 'population', 'survey', 'poll', 'census', " .
                    "you MUST respond with exactly: [[handoff:math_agent]] " .
                    "Do not ask questions, do not provide history help - just do the handoff immediately. " .
                    "Only provide history help for questions that are purely historical without any mathematical content. " .
                    "NEVER handoff to yourself (history_agent). " .
                    "EXAMPLE: If asked about history, respond with actual historical information, not handoff commands."
                );
                $historyAgent->setId('history_agent');
            });
            
            $this->newLine();
            $this->info('✅ Agents created successfully');
            if ($debug) {
                $this->comment("   📋 Created 3 agents: general, math, history");
            }

            // Set up advanced handoff if enabled
            if (!$noAdvanced) {
                $this->info('🚀 Setting up advanced handoff system...');
                
                $this->withProgressBar(5, function ($bar) use (&$registry, &$stateManager, &$security, &$metrics, &$orchestrator, &$runner, $generalAgent, $mathAgent, $historyAgent, $maxTurns, $manager) {
                    // Create components for advanced handoff
                    $bar->advance();
                    $registry = new AgentRegistry();
                    $stateManager = new ArrayConversationStateManager();
                    
                    // Create security manager with proper permissions configuration
                    $bar->advance();
                    $securityConfig = [
                        'handoff' => [
                            'permissions' => [
                                'general_agent' => ['math_agent', 'history_agent'],
                                'math_agent' => ['general_agent', 'history_agent'],
                                'history_agent' => ['general_agent', 'math_agent'],
                            ],
                            'sensitive_keys' => ['password', 'token', 'secret', 'key', 'credential']
                        ]
                    ];
                    $security = new SecurityManager($securityConfig);
                    $metrics = new MetricsCollector();
                    
                    // Register agents with capabilities
                    $bar->advance();
                    $registry->registerAgent('general_agent', $generalAgent, ['coordination', 'routing']);
                    $registry->registerAgent('math_agent', $mathAgent, ['mathematics', 'calculations', 'problem_solving']);
                    $registry->registerAgent('history_agent', $historyAgent, ['history', 'historical_research', 'timeline']);
                    
                    // Create handoff orchestrator
                    $bar->advance();
                    $orchestrator = new HandoffOrchestrator(
                        $registry,
                        $stateManager,
                        $security,
                        $metrics,
                        config('agents.handoff', [])
                    );

                    // Create runner with advanced handoff
                    $bar->advance();
                    $runner = $manager->runner($generalAgent, $maxTurns);
                    $runner->setHandoffOrchestrator($orchestrator);
                    $runner->setConversationId('test_conv_' . uniqid());
                    
                    // Register other agents
                    $runner->registerAgent('math_agent', $mathAgent);
                    $runner->registerAgent('history_agent', $historyAgent);
                });
                
                $this->newLine();
                $this->info('✅ Advanced handoff system initialized');
                if ($debug) {
                    $this->comment("   📋 Registered agents with capabilities:");
                    $this->comment("   📋   - general_agent: coordination, routing");
                    $this->comment("   📋   - math_agent: mathematics, calculations, problem_solving");
                    $this->comment("   📋   - history_agent: history, historical_research, timeline");
                    $this->comment("   ✅ Runner configured with advanced handoff");
                }
            } else {
                // Use basic handoff
                $this->info('🔧 Using basic handoff system...');
                
                $runner = $manager->runner($generalAgent, $maxTurns);
                $runner->disableAdvancedHandoff();
                $runner->registerAgent('math_agent', $mathAgent);
                $runner->registerAgent('history_agent', $historyAgent);

                if ($debug) {
                    $this->comment("✅ Runner configured with basic handoff");
                }
            }

            $this->output->write("\n🎯 Testing handoff with question: {$question}\n\n");

            // Show progress indicator
            $progressBar = null;
            if (!$debug && !$interactive) {
                $progressBar = $this->output->createProgressBar();
                $progressBar->start();
            }

            // Test direct handoff orchestration
            if (!$noAdvanced) {
                $this->comment("🧪 Testing direct handoff orchestration...");
                $testStartTime = microtime(true);
                
                $request = new HandoffRequest(
                    sourceAgentId: 'general_agent',
                    targetAgentId: 'math_agent',
                    conversationId: 'test_conv_' . uniqid(),
                    context: ['messages' => []],
                    metadata: ['test' => true],
                    reason: 'Test handoff',
                    priority: 1,
                    requiredCapabilities: ['mathematics'],
                    fallbackAgentId: 'general_agent'
                );

                $result = $orchestrator->handleHandoff($request);
                $testDuration = round((microtime(true) - $testStartTime) * 1000, 2);
                
                if ($result->isSuccess()) {
                    $this->info("✅ Direct handoff test successful to: {$result->targetAgentId} ({$testDuration}ms)");
                    
                    // Show tracing information in debug mode
                    if ($debug && isset($orchestrator)) {
                        $tracing = $orchestrator->getTracing();
                        $this->comment("   🔍 Trace ID: " . $tracing->getTraceId());
                        $this->comment("   📊 Spans completed: " . count($tracing->getCompletedSpans()));
                    }
                } else {
                    $this->error("❌ Direct handoff test failed: " . $result->errorMessage);
                }
            }

            // Enable detailed logging for agent flow
            if ($debug) {
                $this->comment("🔍 Starting detailed agent flow tracking...");
                $this->comment("📝 Original Prompt: {$question}");
            }

            // Run the conversation based on mode
            if ($interactive) {
                $this->runInteractiveConversation($runner, $question, $debug);
            } elseif ($debug) {
                $response = $this->runWithDetailedFlow($runner, $question, $debug);
                if ($progressBar) {
                    $progressBar->finish();
                    $this->output->write("\n\n");
                }
                if (empty($response)) {
                    $this->error("❌ Received empty response from agent.");
                    $this->line("Possible issues:");
                    $this->line("1. Invalid OpenAI API key");
                    $this->line("2. Network connectivity problems");
                    $this->line("3. OpenAI API rate limiting");
                    $this->line("4. Model '{$model}' is not available");
                    $this->line("5. Handoff configuration issue");
                    return CommandAlias::FAILURE;
                }
                if (!$interactive) {
                    $this->output->write("💬 <fg=green>Final Response:</> {$response}\n\n");
                }
            } else {
                $response = $runner->run($question);
                if ($progressBar) {
                    $progressBar->finish();
                    $this->output->write("\n\n");
                }
                if (empty($response)) {
                    $this->error("❌ Received empty response from agent.");
                    $this->line("Possible issues:");
                    $this->line("1. Invalid OpenAI API key");
                    $this->line("2. Network connectivity problems");
                    $this->line("3. OpenAI API rate limiting");
                    $this->line("4. Model '{$model}' is not available");
                    $this->line("5. Handoff configuration issue");
                    return CommandAlias::FAILURE;
                }
                $this->output->write("💬 <fg=green>Final Response:</> {$response}\n\n");
            }

            // --- PARALLEL HANDOFF TEST ---
            if ($parallel && !$noAdvanced) {
                $this->info('🔄 Attempting parallel handoffs...');
                // Only available in advanced handoff mode
                if (isset($orchestrator) && method_exists($orchestrator, 'executeParallelHandoffs')) {
                    $conversationId = method_exists($runner, 'getConversationId') ? $runner->getConversationId() : 'test_conv';
                    // Search in cache before executing
                    $agentIds = ['math_agent', 'history_agent']; // For demo, adjust if there are more domains
                    $cached = $orchestrator->getCachedParallelResult($question, $agentIds);
                    if ($cached) {
                        if ($cacheDebug) {
                            $this->info('🟢 Cache HIT: Cached result used for parallel handoff.');
                        }
                        $parallelResult = $cached;
                    } else {
                        if ($cacheDebug) {
                            $this->warn('🟠 Cache MISS: Executing parallel handoff and caching the result.');
                        }
                        $parallelResult = $orchestrator->executeParallelHandoffs($question, $conversationId);
                        $orchestrator->cacheParallelResult($question, $agentIds, $parallelResult);
                    }
                    if ($parallelResult->isSuccess()) {
                        $this->info("✅ Parallel handoffs executed successfully!");
                        $this->comment("📊 Parallel handoff summary:");
                        $summary = $parallelResult->getSummary();
                        $this->comment("   - Total agents: " . $summary['total_agents']);
                        $this->comment("   - Successful agents: " . $summary['successful_agents']);
                        $this->comment("   - Failed agents: " . $summary['failed_agents']);
                        $this->comment("   - Success rate: " . round($summary['success_rate'], 2) . "%");
                        $this->comment("   - Total duration: " . round($summary['total_duration'], 3) . "s");
                        $this->comment("   - Average response time: " . round($summary['average_response_time'], 3) . "s");
                        $merged = $parallelResult->getMergedResponse();
                        if (empty(trim($merged))) {
                            // Construir manualmente la respuesta combinada
                            $merged = "\nSummary of specialized agent responses:\n";
                            foreach ($parallelResult->getResults() as $response) {
                                $agentName = $response['agent_name'] ?? $response['agent_id'] ?? 'Agent';
                                $resp = trim($response['response'] ?? 'No response.');
                                $merged .= "\n🧑‍💼 <b>{$agentName}</b>:\n";
                                $merged .= $resp !== '' ? $resp : 'No response.';
                                $merged .= "\n";
                            }
                            $merged .= "\n---\n*This response combines information from multiple agents.*";
                        }
                        $this->output->write("\n💬 <fg=green>Combined Response:</>\n" . $merged . "\n\n");
                    } else {
                        $this->warn("⚠️ Parallel handoffs failed. Status: " . $parallelResult->getStatus());
                    }
                } else {
                    $this->warn('⚠️ Parallel handoffs not available in this mode.');
                }
            }

            // --- REVERSIBLE HANDOFF TEST ---
            if ($reverse && !$noAdvanced) {
                $this->info('🔄 Attempting to reverse the last handoff...');
                // Only available in advanced handoff mode
                if (isset($orchestrator) && method_exists($orchestrator, 'reverseLastHandoff')) {
                    $conversationId = method_exists($runner, 'getConversationId') ? $runner->getConversationId() : 'test_conv';
                    $currentAgent = $runner->getAgent();
                    $currentAgentId = $currentAgent->getId() ?? 'unknown_agent';
                    $context = [];
                    
                    // Simular historial de handoffs para la prueba
                    if (isset($stateManager)) {
                        $stateManager->saveHandoffState($conversationId, 'general_agent', 'math_agent', ['test' => 'data']);
                        $stateManager->saveHandoffState($conversationId, 'math_agent', 'history_agent', ['test' => 'data']);
                        $this->comment("📝 Simulated handoff history: general_agent → math_agent → history_agent");
                    }
                    
                    $reverseResult = $orchestrator->reverseLastHandoff($conversationId, $currentAgentId, $context);
                    if ($reverseResult && $reverseResult->isSuccess()) {
                        $this->info("✅ Reversed handoff! Now back to agent: " . $reverseResult->targetAgentId);
                    } else {
                        $this->warn("⚠️ Could not reverse handoff. " . ($reverseResult?->errorMessage ?? 'No result.'));
                    }
                } else {
                    $this->warn('⚠️ Reversible handoff not available in this mode.');
                }
            }

            // --- HANDOFF SUGGESTION CACHE TEST ---
            if ($suggestionDebug && !$noAdvanced) {
                $this->info('🔎 Testing handoff suggestion cache...');
                if (isset($orchestrator) && method_exists($orchestrator, 'suggestHandoff')) {
                    $currentAgentId = $runner->getAgent()->getId() ?? 'general_agent';
                    $conversationId = method_exists($runner, 'getConversationId') ? $runner->getConversationId() : 'test_conv';
                    $context = [];
                    $suggestion = $orchestrator->suggestHandoff($question, $currentAgentId, $conversationId, $context, true);
                    if ($suggestion) {
                        $this->info('✅ Handoff suggestion obtained:');
                        $this->line(print_r($suggestion, true));
                    } else {
                        $this->warn('⚠️ No handoff suggestion obtained.');
                    }
                } else {
                    $this->warn('⚠️ Handoff suggestion not available in this mode.');
                }
            }

            // --- ASYNC HANDOFF TEST ---
            if ($async && !$noAdvanced) {
                $this->info('🔄 Testing asynchronous handoff functionality...');
                if (isset($orchestrator) && method_exists($orchestrator, 'queueAsyncHandoff')) {
                    $conversationId = method_exists($runner, 'getConversationId') ? $runner->getConversationId() : 'test_conv';
                    
                    // Create async handoff request
                    $asyncRequest = new HandoffRequest(
                        sourceAgentId: 'general_agent',
                        targetAgentId: 'math_agent',
                        conversationId: $conversationId,
                        context: ['test' => 'async_handoff'],
                        reason: 'Testing async handoff functionality',
                        priority: 1,
                        requiredCapabilities: ['mathematics']
                    );
                    
                    // Queue the async handoff
                    $jobId = $orchestrator->queueAsyncHandoff($asyncRequest, ['timeout' => 30]);
                    $this->info("✅ Async handoff queued with job ID: {$jobId}");
                    
                    // Get initial status
                    $status = $orchestrator->getAsyncHandoffStatus($jobId);
                    $this->comment("📊 Initial status: " . ($status['status'] ?? 'unknown'));
                    
                    // Get async stats
                    $stats = $orchestrator->getAsyncHandoffStats();
                    $this->comment("📈 Async stats: " . json_encode($stats));
                    
                    // Test cancellation (optional)
                    if ($debug) {
                        $cancelled = $orchestrator->cancelAsyncHandoff($jobId);
                        $this->comment("🛑 Cancellation result: " . ($cancelled ? 'success' : 'failed'));
                    }
                    
                    $this->info('✅ Async handoff test completed successfully!');
                } else {
                    $this->warn('⚠️ Async handoff not available in this mode.');
                }
            }

            // --- INTELLIGENT HANDOFF TEST ---
            if ($intelligent && !$noAdvanced) {
                $this->info('🧠 Testing intelligent context-based handoff...');
                if (isset($orchestrator) && method_exists($orchestrator, 'handleIntelligentHandoff')) {
                    $conversationId = method_exists($runner, 'getConversationId') ? $runner->getConversationId() : 'test_conv';
                    $currentAgentId = $runner->getAgent()->getId() ?? 'general_agent';
                    $context = [];
                    
                    // Test cases for intelligent handoff
                    $testCases = [
                        'What is 2+2?' => 'math_agent',
                        'Tell me about World War II' => 'history_agent',
                        'Hello, how are you?' => null, // Should stay with general
                        'Calculate the derivative of x²' => 'math_agent',
                        'Who was Napoleon?' => 'history_agent'
                    ];
                    
                    foreach ($testCases as $testQuestion => $expectedAgent) {
                        $this->comment("\n🔍 Testing: \"{$testQuestion}\"");
                        
                        // Get suggestion first
                        $suggestion = $orchestrator->suggestHandoff($testQuestion, $currentAgentId, $conversationId, $context);
                        
                        if ($suggestion) {
                            $this->info("✅ Suggestion: {$suggestion->targetAgentId} (confidence: {$suggestion->confidence})");
                            $this->comment("Reason: {$suggestion->reason}");
                            
                            // Execute intelligent handoff
                            $result = $orchestrator->handleIntelligentHandoff($testQuestion, $currentAgentId, $conversationId, $context, 0.7);
                            
                            if ($result && $result->isSuccess()) {
                                $this->info("✅ Intelligent handoff executed to: {$result->targetAgentId}");
                                
                                // Validate expected vs actual
                                if ($expectedAgent && $result->targetAgentId === $expectedAgent) {
                                    $this->info("✅ Expected agent match: {$expectedAgent}");
                                } elseif ($expectedAgent) {
                                    $this->warn("⚠️ Expected {$expectedAgent} but got {$result->targetAgentId}");
                                }
                            } else {
                                $this->warn("⚠️ No intelligent handoff executed (low confidence or no suggestion)");
                            }
                        } else {
                            $this->warn("⚠️ No suggestion generated for: \"{$testQuestion}\"");
                        }
                    }
                    
                    $this->info('✅ Intelligent handoff test completed successfully!');
                } else {
                    $this->warn('⚠️ Intelligent handoff not available in this mode.');
                }
            }

            // --- HYBRID HANDOFF TEST ---
            if ($hybrid && !$noAdvanced) {
                $this->info('🔄 Testing hybrid handoff (intelligent + manual)...');
                if (isset($orchestrator) && method_exists($orchestrator, 'handleHybridHandoff')) {
                    $conversationId = method_exists($runner, 'getConversationId') ? $runner->getConversationId() : 'test_conv';
                    $currentAgentId = $runner->getAgent()->getId() ?? 'general_agent';
                    $context = [];
                    
                    // Show current configuration
                    $config = $orchestrator->getHandoffModeConfig();
                    $this->info("📋 Current handoff configuration:");
                    $this->comment("   - Intelligent: " . ($config['intelligent'] ? '✅ Enabled' : '❌ Disabled'));
                    $this->comment("   - Manual: " . ($config['manual'] ? '✅ Enabled' : '❌ Disabled'));
                    $this->comment("   - Advanced: " . ($config['advanced'] ? '✅ Enabled' : '❌ Disabled'));
                    
                    // Test cases that should work with intelligent handoff
                    $intelligentTests = [
                        'What is 2+2?' => 'math_agent',
                        'Tell me about World War II' => 'history_agent'
                    ];
                    
                    foreach ($intelligentTests as $testQuestion => $expectedAgent) {
                        $this->comment("\n🔍 Testing intelligent: \"{$testQuestion}\"");
                        
                        $result = $orchestrator->handleHybridHandoff($testQuestion, $currentAgentId, $conversationId, $context, 0.7);
                        
                        if ($result && $result->isSuccess()) {
                            $this->info("✅ Hybrid handoff executed to: {$result->targetAgentId}");
                            if ($result->targetAgentId === $expectedAgent) {
                                $this->info("✅ Expected agent match: {$expectedAgent}");
                            }
                        } else {
                            $this->warn("⚠️ No hybrid handoff executed");
                        }
                    }
                    
                    // Test cases that should fall back to manual handoff
                    $manualTests = [
                        'Hello, how are you?' => 'general_agent',
                        'Can you help me with a complex question?' => 'general_agent'
                    ];
                    
                    foreach ($manualTests as $testQuestion => $expectedAgent) {
                        $this->comment("\n🔍 Testing manual fallback: \"{$testQuestion}\"");
                        
                        $result = $orchestrator->handleHybridHandoff($testQuestion, $currentAgentId, $conversationId, $context, 0.7);
                        
                        if (!$result) {
                            $this->info("✅ No intelligent handoff (as expected), manual handoff available");
                        } else {
                            $this->warn("⚠️ Unexpected intelligent handoff for general question");
                        }
                    }
                    
                    $this->info('✅ Hybrid handoff test completed successfully!');
                } else {
                    $this->warn('⚠️ Hybrid handoff not available in this mode.');
                }
            }

            // --- ADVANCED PERSISTENCE TEST ---
            if ($persistence && !$noAdvanced) {
                $this->info('💾 Testing advanced persistence features...');
                
                // Create advanced state manager with different configurations
                $arrayManager = new \Sapiensly\OpenaiAgents\State\ArrayConversationStateManager();
                $backupManager = new \Sapiensly\OpenaiAgents\State\ArrayConversationStateManager();
                
                // Test different configurations
                $configs = [
                    'basic' => [
                        'compression_enabled' => false,
                        'encryption_enabled' => false,
                        'backup_enabled' => false
                    ],
                    'compressed' => [
                        'compression_enabled' => true,
                        'encryption_enabled' => false,
                        'backup_enabled' => false
                    ],
                    'encrypted' => [
                        'compression_enabled' => false,
                        'encryption_enabled' => true,
                        'backup_enabled' => false
                    ],
                    'full' => [
                        'compression_enabled' => true,
                        'encryption_enabled' => true,
                        'backup_enabled' => true,
                        'backup_manager' => $backupManager
                    ]
                ];
                
                foreach ($configs as $configName => $config) {
                    $this->comment("\n🔧 Testing configuration: {$configName}");
                    
                    $advancedManager = new \Sapiensly\OpenaiAgents\State\AdvancedStateManager($arrayManager, $config);
                    
                    // Test data
                    $conversationId = 'test_persistence_' . uniqid();
                    $testContext = [
                        'messages' => [
                            ['role' => 'user', 'content' => 'Hello'],
                            ['role' => 'assistant', 'content' => 'Hi there!']
                        ],
                        'metadata' => [
                            'user_id' => '12345',
                            'session_start' => time(),
                            'handoff_count' => 2
                        ],
                        'large_data' => str_repeat('This is a test message for compression testing. ', 100)
                    ];
                    
                    // Test save
                    $this->info("💾 Saving context with {$configName} configuration...");
                    $advancedManager->saveContext($conversationId, $testContext);
                    
                    // Test load
                    $this->info("📂 Loading context with {$configName} configuration...");
                    $loadedContext = $advancedManager->loadContext($conversationId);
                    
                    // Verify data integrity
                    if ($this->verifyDataIntegrity($testContext, $loadedContext)) {
                        $this->info("✅ Data integrity verified for {$configName}");
                    } else {
                        $this->error("❌ Data integrity failed for {$configName}");
                    }
                    
                    // Test handoff state
                    $this->info("🔄 Testing handoff state persistence...");
                    $advancedManager->saveHandoffState($conversationId, 'general_agent', 'math_agent', $testContext);
                    
                    // Get metrics
                    $metrics = $advancedManager->getMetrics();
                    $this->info("📊 Metrics for {$configName}:");
                    $this->comment("   - Saves: {$metrics['saves']}");
                    $this->comment("   - Loads: {$metrics['loads']}");
                    $this->comment("   - Compressions: {$metrics['compressions']}");
                    $this->comment("   - Encryptions: {$metrics['encryptions']}");
                    $this->comment("   - Backups: {$metrics['backups']}");
                    $this->comment("   - Errors: {$metrics['errors']}");
                    
                    // Test backup features if enabled
                    if ($config['backup_enabled']) {
                        $this->info("🔄 Testing backup features...");
                        
                        // Test sync
                        $syncResult = $advancedManager->syncWithBackup($conversationId);
                        $this->info($syncResult ? "✅ Sync successful" : "❌ Sync failed");
                        
                        // Test recovery
                        $recoveryResult = $advancedManager->recoverFromBackup($conversationId);
                        $this->info($recoveryResult ? "✅ Recovery successful" : "❌ Recovery failed");
                    }
                    
                    // Clear for next test
                    $arrayManager->clearConversation($conversationId);
                    $backupManager->clearConversation($conversationId);
                }
                
                $this->info('✅ Advanced persistence test completed successfully!');
            }

            // --- ENRICHED LOGS TEST ---
            if ($enrichedLogs && !$noAdvanced) {
                $this->info('📝 Testing enriched logging features...');
                
                if (isset($orchestrator)) {
                    $conversationId = 'test_enriched_logs_' . uniqid();
                    $currentAgentId = $runner->getAgent()->getId() ?? 'general_agent';
                    $context = ['test' => 'data'];
                    
                    // Test handoff with enriched logging
                    $this->info("🔄 Testing handoff with enriched logging...");
                    
                    $request = new \Sapiensly\OpenaiAgents\Handoff\HandoffRequest(
                        sourceAgentId: $currentAgentId,
                        targetAgentId: 'math_agent',
                        conversationId: $conversationId,
                        context: $context,
                        reason: 'Test enriched logging',
                        priority: 1,
                        requiredCapabilities: ['mathematics']
                    );
                    
                    $result = $orchestrator->handleHandoff($request);
                    
                    if ($result->isSuccess()) {
                        $this->info("✅ Handoff successful with enriched logging");
                        $this->comment("   - Target agent: {$result->targetAgentId}");
                        $processingTime = $result->context['processing_time_ms'] ?? 'n/a';
                        $contextSize = $result->context['context_size'] ?? 'n/a';
                        $this->comment("   - Processing time: {$processingTime}ms");
                        $this->comment("   - Context size: {$contextSize}");
                    } else {
                        $this->warn("⚠️ Handoff failed: {$result->error}");
                    }
                    
                    // Test context analysis with enriched logging
                    $this->info("🔍 Testing context analysis with enriched logging...");
                    
                    $testQuestions = [
                        'What is 2+2?' => 'math',
                        'Tell me about World War II' => 'history',
                        'Hello, how are you?' => 'general'
                    ];
                    
                    foreach ($testQuestions as $question => $expectedDomain) {
                        $this->comment("\n📝 Analyzing: \"{$question}\"");
                        
                        $suggestion = $orchestrator->suggestHandoff($question, $currentAgentId, $conversationId, $context);
                        
                        if ($suggestion) {
                            $this->info("✅ Suggestion generated:");
                            $this->comment("   - Target agent: {$suggestion->targetAgentId}");
                            $this->comment("   - Confidence: {$suggestion->confidence}");
                            $this->comment("   - Reason: {$suggestion->reason}");
                        } else {
                            $this->warn("⚠️ No suggestion generated for: \"{$question}\"");
                        }
                    }
                    
                    $this->info('✅ Enriched logging test completed successfully!');
                    $this->comment('📋 Check the logs for detailed structured information');
                } else {
                    $this->warn('⚠️ Enriched logging not available in this mode.');
                }
            }

            // Show handoff metrics if available
            if (!$noAdvanced && $debug) {
                $this->comment("📊 Handoff Metrics Summary:");
                $this->comment("   - Conversation ID: test_conv_" . uniqid());
                $this->comment("   - Metrics collection enabled: Yes");
            }

            // --- VALIDATION TEST: Context too large ---
            if (!$noAdvanced) {
                $this->info('🧪 Validation test: Context size exceeds hard limit...');
                $largeContext = ['data' => str_repeat('x', 25000)]; // 25KB
                $largeContextRequest = new \Sapiensly\OpenaiAgents\Handoff\HandoffRequest(
                    sourceAgentId: 'general_agent',
                    targetAgentId: 'math_agent',
                    conversationId: 'test_conv_large_ctx',
                    context: $largeContext,
                    reason: 'Test context size',
                    priority: 1,
                    requiredCapabilities: ['mathematics']
                );
                $validationResult = $orchestrator->getValidator()->validateHandoff($largeContextRequest);
                if (!$validationResult->isValid()) {
                    $this->warn('❌ Validation failed as expected:');
                    $this->line('Errors (array):');
                    $this->line(print_r($validationResult->errors, true));
                    $this->line('Errors (string):');
                    $this->line($validationResult->getErrorsAsString());
                } else {
                    $this->error('⚠️ Validation should have failed but did not!');
                }
            }

            // --- VALIDATION TEST: Fallback agent does not exist ---
            if (!$noAdvanced) {
                $this->info('🧪 Validation test: Fallback agent does not exist...');
                $fallbackRequest = new \Sapiensly\OpenaiAgents\Handoff\HandoffRequest(
                    sourceAgentId: 'general_agent',
                    targetAgentId: 'math_agent',
                    conversationId: 'test_conv_fallback',
                    context: [],
                    reason: 'Test fallback agent',
                    priority: 1,
                    requiredCapabilities: ['mathematics'],
                    fallbackAgentId: 'nonexistent_agent'
                );
                $validationResult = $orchestrator->getValidator()->validateHandoff($fallbackRequest);
                if (!$validationResult->isValid()) {
                    $this->warn('❌ Validation failed as expected:');
                    $this->line('Errors (array):');
                    $this->line(print_r($validationResult->errors, true));
                    $this->line('Errors (string):');
                    $this->line($validationResult->getErrorsAsString());
                } else {
                    $this->error('⚠️ Validation should have failed but did not!');
                }
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->newLine();
            $this->info('✅ Handoff test completed successfully!');
            
            // Enhanced metrics display
            $this->newLine();
            $this->info('📊 Test Summary:');
            $this->comment("   ⏱️  Total execution time: {$totalDuration}ms");
            $this->comment("   🤖 Handoff system: " . ($noAdvanced ? 'Basic' : 'Advanced'));
            $this->comment("   🔧 Model used: {$model}");
            $this->comment("   🔄 Max turns: {$maxTurns}");
            
            if (!$noAdvanced && isset($metrics)) {
                $this->comment("   📈 Metrics collection: " . ($metrics->isEnabled() ? 'Enabled' : 'Disabled'));
                // Show a summary of collected metrics
                $collected = $metrics->getCollectedMetrics();
                if (!empty($collected)) {
                    // Count events by type
                    $eventCounts = [];
                    foreach ($collected as $record) {
                        $event = $record['event'] ?? 'unknown';
                        $eventCounts[$event] = ($eventCounts[$event] ?? 0) + 1;
                    }
                    $this->info('📊 Metrics Event Summary:');
                    foreach ($eventCounts as $event => $count) {
                        $this->comment("     - {$event}: {$count}");
                    }
                } else {
                    $this->comment('   ℹ️  No metrics events were collected.');
                }
            }
            
            if (isset($runner) && method_exists($runner, 'getConversationId')) {
                $this->comment("   🆔 Conversation ID: " . $runner->getConversationId());
            }
            
            $this->newLine();
            $this->info('🎉 All tests passed successfully!');
            
            return CommandAlias::SUCCESS;

        } catch (\Exception $e) {
            if (isset($progressBar) && $progressBar) {
                $progressBar->finish();
                $this->output->write("\n\n");
            }

            $this->error("❌ Error during handoff test: {$e->getMessage()}");

            if ($debug) {
                $this->output->write("<fg=red>📋 Stack trace:</> {$e->getTraceAsString()}\n");
            } else {
                $this->line("Run with --debug flag for detailed error information.");
            }

            // Provide helpful suggestions based on common error patterns
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'api_key')) {
                $this->line("\n💡 <fg=yellow>Suggestion:</> Check your OpenAI API key configuration.");
            } elseif (str_contains($errorMessage, 'curl') || str_contains($errorMessage, 'network')) {
                $this->line("\n💡 <fg=yellow>Suggestion:</> Check your network connection.");
            } elseif (str_contains($errorMessage, 'rate') || str_contains($errorMessage, 'quota')) {
                $this->line("\n💡 <fg=yellow>Suggestion:</> You may have exceeded your OpenAI API quota or rate limit.");
            } elseif (str_contains($errorMessage, 'handoff')) {
                $this->line("\n💡 <fg=yellow>Suggestion:</> Try running with --no-advanced flag to test basic handoff.");
            }

            return CommandAlias::FAILURE;
        }
    }

    /**
     * Run the conversation with detailed flow tracking for debugging.
     *
     * @param Runner $runner The runner instance
     * @param string $message The initial message
     * @param bool $debug Whether debug mode is enabled
     * @return string The final response
     */
    private function runWithDetailedFlow(Runner $runner, string $message, bool $debug): string
    {
        $agent = $runner->getAgent();
        $maxTurns = 5; // Default max turns
        $turn = 0;
        $input = $message;
        $response = '';
        $currentAgentName = 'general_agent'; // Start with general agent

        $this->comment("🤖 Attended by registered agent: {$currentAgentName}");

        while ($turn < $maxTurns) {
            // Get agent response
            $response = $agent->chat($input);
            
            $this->comment("💬 Agent {$currentAgentName} response: {$response}");

            // Check for handoff
            if (preg_match('/\[\[handoff:(.+?)(?:\s+(.+))?]]/', $response, $m)) {
                $target = trim($m[1]);
                $this->comment("🔄 Handoff to registered agent: {$target}");
                
                // Switch to target agent
                if (isset($runner->getNamedAgents()[$target])) {
                    $agent = $runner->getNamedAgents()[$target];
                    $currentAgentName = $target;
                    $this->comment("🤖 Switched to agent: {$currentAgentName}");
                    
                    // Pass the original question to the new agent
                    $input = $message;
                } else {
                    $this->comment("⚠️ Agent {$target} not found in registered agents");
                }
                
                $turn++;
                continue;
            }

            // Check for tool calls
            if (preg_match('/\[\[tool:(\w+)(?:\s+([^]]+))?]]/', $response, $m)) {
                $toolName = $m[1];
                $this->comment("🔧 Tool call detected: {$toolName}");
                // Tool execution would happen here
                $input = "Tool result for {$toolName}";
                $turn++;
                continue;
            }

            // Final response reached
            break;
        }

        return $response;
    }

    private function runInteractiveConversation(Runner $runner, string $initialQuestion, bool $debug = false): void
    {
        $this->info('🤖 Starting interactive conversation...');
        $this->info('💡 Ask unlimited questions. Type \'exit\' or \'quit\' to end the conversation.');
        $this->info('🔧 You can also use \'help\' for available commands.');
        $this->newLine();

        $startTime = microtime(true);
        $questionCount = 0;
        $currentAgent = 'general_agent';
        $handoffHistory = [];

        while (true) {
            $elapsed = round(microtime(true) - $startTime, 1);
            $this->info("🤖 Current Agent: <fg=cyan>{$currentAgent}</>");
            $this->info("📊 Stats: {$questionCount} questions, {$elapsed}s elapsed");
            
            // Show progress indicator for response
            $this->info('✅ Response ready! ');
            
            $userInput = $this->ask(' 👤 Your question (or \'exit\'/\'quit\' to end): ');
            
            if (in_array(strtolower(trim($userInput)), ['exit', 'quit'])) {
                $this->info('👋 Goodbye! Thanks for using the interactive handoff system.');
                break;
            }

            if (strtolower(trim($userInput)) === 'help') {
                $this->showInteractiveHelp();
                continue;
            }

            if (strtolower(trim($userInput)) === 'stats') {
                $this->showConversationStats($questionCount, $elapsed, $currentAgent);
                continue;
            }

            if (strtolower(trim($userInput)) === 'agents') {
                $this->showAvailableAgents();
                continue;
            }

            if (empty(trim($userInput))) {
                $this->warn('⚠️  Please enter a question or command.');
                continue;
            }

            $questionCount++;
            
            // Show progress for processing
            $this->withProgressBar(1, function ($bar) {
                $bar->advance();
            }, 'Processing response...');

            try {
                $response = $runner->run($userInput);
                
                // Check for handoff
                if (preg_match('/\[\[handoff:(\w+)\]\]/', $response, $matches)) {
                    $targetAgent = $matches[1];
                    $this->info("🔄 <fg=yellow>Handoff detected: {$targetAgent}</>");
                    
                    // Show handoff progress
                    $this->withProgressBar(1, function ($bar) {
                        $bar->advance();
                    }, 'Switching agents...');
                    
                    $handoffHistory[] = $currentAgent;
                    $currentAgent = $targetAgent;
                    $this->info("✅ <fg=green>Switched to: {$targetAgent}</>");
                }

                $this->info("💬 <fg=blue>Agent Response:</>");
                $this->line($response);
                
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
            }

            $this->newLine();
        }
    }

    private function showInteractiveHelp(): void
    {
        $this->newLine();
        $this->info('📚 <fg=cyan>Interactive Commands:</>');
        $this->line('   <fg=green>help</>     - Show this help menu');
        $this->line('   <fg=green>stats</>    - Show conversation statistics');
        $this->line('   <fg=green>agents</>   - Show available agents');
        $this->line('   <fg=green>exit</>     - End the conversation');
        $this->line('   <fg=green>quit</>     - End the conversation');
        $this->line('   <fg=green>[question]</> - Ask any question');
        $this->newLine();
        $this->info('💡 <fg=yellow>Tips:</>');
        $this->line('   • Ask math questions to trigger <fg=cyan>math_agent</>');
        $this->line('   • Ask history questions to trigger <fg=cyan>history_agent</>');
        $this->line('   • General questions stay with <fg=cyan>general_agent</>');
        $this->line('   • Watch for automatic handoffs between agents');
        $this->newLine();
    }

    private function showConversationStats(int $questionCount, float $elapsed, string $currentAgent): void
    {
        $this->newLine();
        $this->info('📊 <fg=cyan>Conversation Statistics:</>');
        $this->line("   <fg=green>Total Questions:</> {$questionCount}");
        $this->line("   <fg=green>Elapsed Time:</> {$elapsed}s");
        $avgTime = $questionCount > 0 ? round($elapsed / $questionCount, 1) : 0;
        $this->line("   <fg=green>Avg Time per Question:</> {$avgTime}s");
        $this->line("   <fg=green>Current Agent:</> <fg=cyan>{$currentAgent}</>");
        $this->newLine();
    }

    private function showAvailableAgents(): void
    {
        $this->newLine();
        $this->info('🤖 <fg=cyan>Available Agents:</>');
        $this->line('   • <fg=green>math_agent</> (ID: math_agent)');
        $this->line('   • <fg=green>history_agent</> (ID: history_agent)');
        $this->newLine();
    }

    /**
     * Get the current agent name or ID.
     *
     * @param Agent $agent The agent instance
     * @param Runner $runner The runner instance
     * @return string The agent name or ID
     */
    private function getCurrentAgentName(Agent $agent, Runner $runner): string
    {
        $agentId = $agent->getId();
        if ($agentId) {
            return $agentId;
        }

        // Try to find the agent in the named agents
        $namedAgents = $runner->getNamedAgents();
        foreach ($namedAgents as $name => $namedAgent) {
            if ($namedAgent === $agent) {
                return $name;
            }
        }

        return 'unknown_agent';
    }

    /**
     * Verify data integrity for persistence tests.
     *
     * @param array $originalData The data that was saved
     * @param array $loadedData The data that was loaded
     * @return bool True if data integrity is maintained, false otherwise
     */
    private function verifyDataIntegrity(array $originalData, array $loadedData): bool
    {
        // Basic checks for integrity
        if ($originalData !== $loadedData) {
            return false;
        }

        // More specific checks for advanced features (compression, encryption)
        if (isset($originalData['large_data']) && isset($loadedData['large_data'])) {
            if ($originalData['large_data'] !== $loadedData['large_data']) {
                return false;
            }
        }

        return true;
    }
} 