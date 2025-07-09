<?php

/**
 * TestLevel3Command - Multi-Agents Testing
 * 
 * Purpose: Tests Level 3 of the progressive enhancement architecture - multiple
 * specialized agents that can collaborate through handoff and workflows. This
 * command validates agent collaboration, specialization, and coordination.
 * 
 * Level 3 Concept: Multiple specialized agents collaborate (handoff, workflows).
 * Each agent can have its own tools, persona, and configuration. This level
 * adds collaboration and specialization capabilities.
 * 
 * Features Tested:
 * - Multiple specialized agents (customer service, technical, sales)
 * - Agent collaboration and handoff simulation
 * - Workflow simulation with agent coordination
 * - Specialized system prompts and personas
 * - Tool integration per agent specialization
 * - Performance measurement and timing
 * 
 * Usage:
 * - Basic test: php artisan agent:test-level3 "I need help with a technical issue"
 * - Custom system: php artisan agent:test-level3 "Help me" --system="You are a coordinator"
 * - Different model: php artisan agent:test-level3 "Customer inquiry" --model=gpt-4
 * - With tracing: php artisan agent:test-level3 "Hello" --trace
 * 
 * Test Scenarios:
 * 1. Multiple specialized agents (customer service, technical, sales)
 * 2. Agent collaboration simulation
 * 3. Workflow simulation with agent coordination
 * 4. Performance timing for each test
 * 
 * Agent Specializations:
 * - Customer Service Agent: Handles customer inquiries and issues
 * - Technical Support Agent: Handles technical problems and solutions
 * - Sales Agent: Handles pricing, plans, and sales inquiries
 * 
 * Collaboration Features:
 * - Agent handoff simulation
 * - Workflow coordination
 * - Specialized tool usage per agent
 * - Context preservation across agents
 */

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;

class TestLevel3Command extends Command
{
    protected $signature = 'agent:test-level3
                            {message : The message to send to the agent}
                            {--system= : Optional custom system prompt}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}
                            {--trace : Enable tracing for debugging}';

    protected $description = 'Test Level 3: Multi-Agents - Agent collaboration and handoff';

    public function handle(AgentManager $manager): int
    {
        $this->info('🧪 Testing Level 3: Multi-Agents');
        $this->line('Concept: Multiple specialized agents collaborate (handoff, workflows). Each agent can have its own tools, persona, and config.');
        $this->line('');

        $message = $this->argument('message');
        $systemPrompt = $this->option('system') ?: 'You are a helpful assistant that can collaborate with other agents.';
        $model = $this->option('model');
        $trace = $this->option('trace');

        // Verify OpenAI API key
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('❌ OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return self::FAILURE;
        }

        try {
            $this->info('📝 Test 1: Multiple Specialized Agents');
            $this->line("Input: {$message}");
            
            // Create specialized agents
            $customerServiceAgent = Agent::create([
                'system_prompt' => 'You are a customer service representative. Help customers with their inquiries and issues.',
                'model' => $model,
                'tools' => ['echo', 'date'],
            ]);
            
            $technicalAgent = Agent::create([
                'system_prompt' => 'You are a technical support specialist. Help with technical issues and provide solutions.',
                'model' => $model,
                'tools' => ['calculator', 'echo'],
            ]);
            
            $salesAgent = Agent::create([
                'system_prompt' => 'You are a sales representative. Help with pricing, plans, and sales inquiries.',
                'model' => $model,
                'tools' => ['calculator', 'date'],
            ]);
            
            $this->line("Testing Customer Service Agent:");
            $startTime = microtime(true);
            $response1 = $customerServiceAgent->chat($message);
            $duration1 = microtime(true) - $startTime;
            $this->line("Response: {$response1}");
            $this->line("Duration: " . number_format($duration1, 3) . "s");
            $this->line('');
            
            $this->line("Testing Technical Agent:");
            $startTime = microtime(true);
            $response2 = $technicalAgent->chat($message);
            $duration2 = microtime(true) - $startTime;
            $this->line("Response: {$response2}");
            $this->line("Duration: " . number_format($duration2, 3) . "s");
            $this->line('');
            
            $this->line("Testing Sales Agent:");
            $startTime = microtime(true);
            $response3 = $salesAgent->chat($message);
            $duration3 = microtime(true) - $startTime;
            $this->line("Response: {$response3}");
            $this->line("Duration: " . number_format($duration3, 3) . "s");
            $this->line('');

            $this->info('📝 Test 2: Agent Collaboration Simulation');
            $this->line("Input: {$message}");
            
            // Simulate agent collaboration
            $runner = Agent::runner();
            
            // Register tools that simulate different agent capabilities
            $runner->registerTool('customer_service', function($args) {
                $query = $args['query'] ?? '';
                return "Customer Service Agent: I can help you with your customer service inquiry: {$query}";
            });
            
            $runner->registerTool('technical_support', function($args) {
                $issue = $args['issue'] ?? '';
                return "Technical Support Agent: I can help you with your technical issue: {$issue}";
            });
            
            $runner->registerTool('sales_inquiry', function($args) {
                $inquiry = $args['inquiry'] ?? '';
                return "Sales Agent: I can help you with your sales inquiry: {$inquiry}";
            });
            
            $startTime = microtime(true);
            $response4 = $runner->run($message);
            $duration4 = microtime(true) - $startTime;
            
            $this->line("Response: {$response4}");
            $this->line("Duration: " . number_format($duration4, 3) . "s");
            $this->line('');

            $this->info('📝 Test 3: Agent Workflow Simulation');
            $this->line("Input: {$message}");
            
            // Simulate a workflow where agents pass information to each other
            $workflowRunner = Agent::runner();
            
            $workflowRunner->registerTool('escalate_to_technical', function($args) {
                $issue = $args['issue'] ?? '';
                return "Escalating to Technical Support: {$issue}";
            });
            
            $workflowRunner->registerTool('escalate_to_sales', function($args) {
                $inquiry = $args['inquiry'] ?? '';
                return "Escalating to Sales: {$inquiry}";
            });
            
            $workflowRunner->registerTool('escalate_to_customer_service', function($args) {
                $inquiry = $args['inquiry'] ?? '';
                return "Escalating to Customer Service: {$inquiry}";
            });
            
            $startTime = microtime(true);
            $response5 = $workflowRunner->run($message);
            $duration5 = microtime(true) - $startTime;
            
            $this->line("Response: {$response5}");
            $this->line("Duration: " . number_format($duration5, 3) . "s");
            $this->line('');

            $this->info('✅ Level 3 Tests Completed Successfully!');
            $this->line('Features tested:');
            $this->line('  ✓ Multiple specialized agents');
            $this->line('  ✓ Agent collaboration');
            $this->line('  ✓ Workflow simulation');
            $this->line('  ✓ Agent handoff simulation');
            $this->line('  ✓ Response timing');
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error during Level 3 test: {$e->getMessage()}");
            if ($trace) {
                $this->line("Stack trace: {$e->getTraceAsString()}");
            }
            return self::FAILURE;
        }
    }
} 