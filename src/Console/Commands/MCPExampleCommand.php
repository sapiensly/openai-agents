<?php

/**
 * MCPExampleCommand - Model Context Protocol Example
 *
 * Purpose: Demonstrates MCP (Model Context Protocol) functionality with simulated
 * tools and servers. This command shows how to set up MCP servers, register tools,
 * and integrate them with agents for enhanced capabilities.
 *
 * MCP Concept: Model Context Protocol enables AI models to access external data
 * sources, tools, and resources through standardized interfaces, allowing for
 * enhanced capabilities beyond the model's training data.
 *
 * Features Tested:
 * - Simulated MCP server setup (weather, calculator, database)
 * - MCP tool registration and integration
 * - Agent and runner integration with MCP
 * - MCP statistics and debugging
 * - Resource and tool discovery
 *
 * Usage:
 * - Basic: php artisan agent:mcp-example
 * - Custom query: php artisan agent:mcp-example --query="What is the weather in London and calculate 10 * 5?"
 * - Debug mode: php artisan agent:mcp-example --debug
 * - Custom model: php artisan agent:mcp-example --model=gpt-4
 *
 * Test Scenarios:
 * 1. Simulated MCP server setup (weather, calculator, database)
 * 2. MCP tool registration and integration
 * 3. Agent and runner integration with MCP
 * 4. MCP statistics and debugging
 * 5. Resource and tool discovery
 *
 * Simulated Servers:
 * - Weather server: Provides weather information for cities
 * - Calculator server: Performs mathematical calculations
 * - Database server: Executes simulated SQL queries
 *
 * Available Tools:
 * - get_weather: Get current weather for a location
 * - calculate: Perform mathematical calculations
 * - query_database: Execute SQL queries (simulated)
 *
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\AgentManager;
use Sapiensly\OpenaiAgents\MCP\MCPManager;
use Sapiensly\OpenaiAgents\MCP\MCPServer;
use Sapiensly\OpenaiAgents\MCP\MCPResource;
use Sapiensly\OpenaiAgents\MCP\MCPTool;
use Sapiensly\OpenaiAgents\Runner;
use Symfony\Component\Console\Command\Command as CommandAlias;

class MCPExampleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:mcp-example
                            {--query= : Custom query to test MCP functionality}
                            {--debug : Enable detailed debug logging}
                            {--model=gpt-3.5-turbo : The OpenAI model to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrate MCP functionality with simulated tools';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $this->info('🚀 MCP Example: Demonstrating Model Context Protocol functionality');

        // Verify OpenAI API key is configured
        $apiKey = config('agents.api_key');
        if (empty($apiKey) || $apiKey === 'your-api-key-here') {
            $this->error('OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
            return CommandAlias::FAILURE;
        }

        $query = $this->option('query') ?: 'What is the weather in Madrid and calculate 15 * 23?';
        $debug = $this->option('debug');
        $model = $this->option('model');

        if ($debug) {
            $this->comment("Query: {$query}");
            $this->comment("Model: {$model}");
        }

        try {
            // Create MCP Manager with simulated servers
            $mcpManager = new MCPManager([
                'enable_logging' => $debug,
                'auto_discover' => false, // We'll add resources manually
            ]);

            // Setup simulated MCP servers and tools
            $this->setupSimulatedMCPServers($mcpManager, $debug);

            // Create agent and runner
            $agent = $manager->agent([
                'model' => $model,
                'temperature' => 0.7,
            ], 'You are a helpful assistant that can use MCP tools to access external data and perform calculations.');

            $runner = new Runner($agent);
            $runner->setMCPManager($mcpManager);

            // Register MCP tools with the runner
            $this->registerMCPTools($runner, $mcpManager, $debug);

            $this->info('🔧 MCP Setup Complete');
            $this->line("Registered tools: " . implode(', ', array_keys($mcpManager->getTools())));

            // Run the query
            $this->info("\n🤖 Running query: {$query}");
            $this->line("=" . str_repeat("=", strlen($query) + 20));

            $startTime = microtime(true);
            $response = $runner->run($query);
            $executionTime = microtime(true) - $startTime;

            $this->line("\n📝 Response:");
            $this->line($response);

            $this->line("\n⏱️ Execution time: " . number_format($executionTime, 4) . "s");

            // Show MCP statistics
            $this->showMCPStats($mcpManager);

            $this->info('✅ MCP Example completed successfully');
            return CommandAlias::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error during MCP example: {$e->getMessage()}");
            if ($debug) {
                $this->output->write("<fg=red>Stack trace:</> {$e->getTraceAsString()}\n");
            }
            return CommandAlias::FAILURE;
        }
    }

    /**
     * Setup simulated MCP servers
     */
    private function setupSimulatedMCPServers(MCPManager $mcpManager, bool $debug): void
    {
        $this->info('🔧 Setting up simulated MCP servers...');

        // Weather server (simulated)
        $weatherServer = new MCPServer('weather', 'http://localhost:3000/mcp', [
            'headers' => ['Authorization' => 'Bearer test-token'],
            'timeout' => 30,
            'enable_logging' => $debug
        ]);

        // Add weather resources
        $weatherServer->addResource(new MCPResource(
            'current-weather',
            'Get current weather for a location',
            'weather://current',
            [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name',
                    'required' => true,
                    'minLength' => 2
                ],
                'unit' => [
                    'type' => 'string',
                    'description' => 'Temperature unit',
                    'enum' => ['celsius', 'fahrenheit'],
                    'default' => 'celsius'
                ]
            ]
        ));

        $weatherServer->addResource(new MCPResource(
            'weather-forecast',
            'Get weather forecast for a location',
            'weather://forecast',
            [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name',
                    'required' => true
                ],
                'days' => [
                    'type' => 'integer',
                    'description' => 'Number of days',
                    'minimum' => 1,
                    'maximum' => 7,
                    'default' => 3
                ]
            ]
        ));

        $mcpManager->addServer('weather', 'http://localhost:3000/mcp', [
            'headers' => ['Authorization' => 'Bearer test-token']
        ]);

        // Calculator server (simulated)
        $calculatorServer = new MCPServer('calculator', 'http://localhost:3002/mcp', [
            'timeout' => 30,
            'enable_logging' => $debug
        ]);

        $calculatorServer->addResource(new MCPResource(
            'calculate',
            'Perform mathematical calculations',
            'calculator://calculate',
            [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Mathematical expression',
                    'required' => true
                ],
                'precision' => [
                    'type' => 'integer',
                    'description' => 'Decimal precision',
                    'minimum' => 0,
                    'maximum' => 10,
                    'default' => 2
                ]
            ]
        ));

        $mcpManager->addServer('calculator', 'http://localhost:3002/mcp');

        // Database server (simulated)
        $databaseServer = new MCPServer('database', 'http://localhost:3001/mcp', [
            'headers' => ['X-API-Key' => 'test-secret'],
            'timeout' => 30,
            'enable_logging' => $debug
        ]);

        $databaseServer->addResource(new MCPResource(
            'query-database',
            'Execute SQL queries',
            'database://query',
            [
                'sql' => [
                    'type' => 'string',
                    'description' => 'SQL query',
                    'required' => true
                ],
                'params' => [
                    'type' => 'array',
                    'description' => 'Query parameters',
                    'default' => []
                ]
            ]
        ));

        $mcpManager->addServer('database', 'http://localhost:3001/mcp', [
            'headers' => ['X-API-Key' => 'test-secret']
        ]);

        if ($debug) {
            $this->line('✅ Simulated servers configured');
        }
    }

    /**
     * Register MCP tools with the runner
     */
    private function registerMCPTools(Runner $runner, MCPManager $mcpManager, bool $debug): void
    {
        $this->info('🛠️ Registering MCP tools...');

        // Weather tool
        $weatherResource = new MCPResource(
            'current-weather',
            'Get current weather for a location',
            'weather://current',
            [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name',
                    'required' => true
                ]
            ]
        );

        $weatherTool = MCPTool::withProcessor(
            'get_weather',
            $weatherResource,
            $mcpManager->getServer('weather'),
            function ($result, $parameters) {
                $location = $parameters['location'];
                $weatherData = [
                    'Madrid' => 'sunny, 25°C',
                    'Barcelona' => 'partly cloudy, 22°C',
                    'Valencia' => 'clear, 28°C',
                    'Seville' => 'hot, 32°C',
                    'Bilbao' => 'rainy, 18°C'
                ];
                
                $weather = $weatherData[$location] ?? 'unknown, 20°C';
                return "Weather in {$location}: {$weather}";
            }
        );

        $mcpManager->addTool($weatherTool);

        // Calculator tool
        $calculatorResource = new MCPResource(
            'calculate',
            'Perform mathematical calculations',
            'calculator://calculate',
            [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Mathematical expression',
                    'required' => true
                ]
            ]
        );

        $calculatorTool = MCPTool::withProcessor(
            'calculate',
            $calculatorResource,
            $mcpManager->getServer('calculator'),
            function ($result, $parameters) {
                $expression = $parameters['expression'];
                
                // Simple and safe evaluation (in production, use a proper math library)
                $expression = preg_replace('/[^0-9+\-*/().\s]/', '', $expression);
                
                // Very basic evaluation for demonstration
                if (preg_match('/^(\d+)\s*([+\-*/])\s*(\d+)$/', $expression, $matches)) {
                    $a = (int)$matches[1];
                    $b = (int)$matches[3];
                    $op = $matches[2];
                    
                    switch ($op) {
                        case '+': $result = $a + $b; break;
                        case '-': $result = $a - $b; break;
                        case '*': $result = $a * $b; break;
                        case '/': $result = $b != 0 ? $a / $b : 'Error: Division by zero'; break;
                        default: $result = 'Error: Invalid operation';
                    }
                } else {
                    $result = 'Error: Invalid expression format';
                }
                
                return "Result of {$expression}: {$result}";
            }
        );

        $mcpManager->addTool($calculatorTool);

        // Database tool
        $databaseResource = new MCPResource(
            'query-database',
            'Execute SQL queries',
            'database://query',
            [
                'sql' => [
                    'type' => 'string',
                    'description' => 'SQL query',
                    'required' => true
                ]
            ]
        );

        $databaseTool = MCPTool::withProcessor(
            'query_database',
            $databaseResource,
            $mcpManager->getServer('database'),
            function ($result, $parameters) {
                $sql = $parameters['sql'];
                
                // Simulate database responses
                $responses = [
                    'SELECT * FROM users' => 'Found 150 users in the database',
                    'SELECT COUNT(*) FROM orders' => 'Total orders: 1,247',
                    'SELECT * FROM products' => 'Found 89 products in inventory',
                    'SELECT * FROM customers' => 'Found 342 customers in the system'
                ];
                
                $response = $responses[$sql] ?? "Query executed: {$sql} (simulated result)";
                return $response;
            }
        );

        $mcpManager->addTool($databaseTool);

        if ($debug) {
            $this->line('  Registered weather, calculator, and database tools');
        }
    }

    /**
     * Show MCP statistics
     */
    private function showMCPStats(MCPManager $mcpManager): void
    {
        $this->info('📊 MCP Statistics:');
        
        $stats = $mcpManager->getStats();
        $this->line("  Total servers: {$stats['total_servers']}");
        $this->line("  Enabled servers: {$stats['enabled_servers']}");
        $this->line("  Total resources: {$stats['total_resources']}");
        $this->line("  Total tools: {$stats['total_tools']}");
        $this->line("  Total calls: {$stats['total_calls']}");
        $this->line("  Successful calls: {$stats['successful_calls']}");
        $this->line("  Failed calls: {$stats['failed_calls']}");

        $this->line("\n🛠️ Available MCP Tools:");
        foreach ($mcpManager->getTools() as $tool) {
            $this->line("  - {$tool->getName()}: {$tool->getResource()->getDescription()}");
        }
    }
} 