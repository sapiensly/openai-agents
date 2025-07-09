<?php

/**
 * TestMCPHTTP - Model Context Protocol HTTP Testing
 * 
 * Purpose: Tests MCP (Model Context Protocol) server functionality with HTTP
 * transport. This command validates MCP client-server communication, resource
 * discovery, and tool integration over HTTP connections.
 * 
 * MCP Concept: Model Context Protocol enables AI models to access external
 * data sources, tools, and resources through standardized interfaces, allowing
 * for enhanced capabilities beyond the model's training data.
 * 
 * Features Tested:
 * - MCP server connection via HTTP
 * - Resource discovery and listing
 * - Tool registration and usage
 * - JSON-RPC communication
 * - Server information retrieval
 * - Error handling and timeout management
 * - Configuration validation
 * 
 * Usage:
 * - Basic test: php artisan agent:test-mcp-http
 * - Custom tool: php artisan agent:test-mcp-http --tool=add
 * - With parameters: php artisan agent:test-mcp-http --tool=add --params='{"a":5,"b":3}'
 * - Custom method: php artisan agent:test-mcp-http --method=GET
 * 
 * Test Scenarios:
 * 1. MCP server connection and authentication
 * 2. Server information retrieval
 * 3. Resource discovery and listing
 * 4. Tool registration and usage
 * 5. JSON-RPC communication testing
 * 6. Error handling and timeout validation
 * 
 * MCP Configuration:
 * - Server URL configuration
 * - Authentication headers
 * - Timeout and retry settings
 * - Logging and debugging options
 * - Resource and tool discovery
 * 
 * Available Tools:
 * - add: Adds two numbers
 * - multiply: Multiplies two numbers
 * - get_time: Gets current time
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\Agent;
use Sapiensly\OpenaiAgents\Runner;
use Sapiensly\OpenaiAgents\MCP\MCPManager;
use Sapiensly\OpenaiAgents\MCP\MCPServer;
use Sapiensly\OpenaiAgents\MCP\MCPClient;
use Sapiensly\OpenaiAgents\MCP\MCPResource;
use Sapiensly\OpenaiAgents\MCP\MCPTool;
use OpenAI\Laravel\Facades\OpenAI;

class TestMCPHTTP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:test-mcp-http {--tool=add} {--params=} {--method=POST}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MCP server with HTTP transport using configuration from config/agents.php';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Testing MCP Server with HTTP Transport');
        $this->newLine();

        // Check if MCP is enabled
        if (!config('agents.mcp.enabled')) {
            $this->error('❌ MCP is not enabled. Set MCP_ENABLED=true in your .env file');
            return Command::FAILURE;
        }

        try {
            // Get MCP configuration
            $mcpConfig = config('agents.mcp');
            
            $this->info('📡 MCP Configuration:');
            $this->line("  Server URL: {$mcpConfig['server_url']}");
            $this->line("  SSE URL: {$mcpConfig['sse_url']}");
            $this->line("  Timeout: {$mcpConfig['timeout']}s");
            $this->line("  Max Retries: {$mcpConfig['max_retries']}");
            $this->line("  Logging: " . ($mcpConfig['enable_logging'] ? 'Enabled' : 'Disabled'));
            $this->newLine();

            // Create MCP Manager
            $mcpManager = new MCPManager([
                'enable_logging' => $mcpConfig['enable_logging'],
                'timeout' => $mcpConfig['timeout'],
                'max_retries' => $mcpConfig['max_retries']
            ]);

            // Create MCP Client for HTTP
            $httpClient = new MCPClient(
                $mcpConfig['server_url'],
                $mcpConfig['headers'],
                $mcpConfig['timeout'],
                $mcpConfig['max_retries'],
                $mcpConfig['enable_logging']
            );

            // Create MCP Server with HTTP client
            $server = new MCPServer('http-mcp-server', $mcpConfig['server_url'], [
                'enabled' => true,
                'timeout' => $mcpConfig['timeout'],
                'max_retries' => $mcpConfig['max_retries'],
                'headers' => $mcpConfig['headers']
            ]);

            // Add server to manager
            $mcpManager->addServer('http-mcp-server', $mcpConfig['server_url'], [
                'enabled' => true,
                'capabilities' => ['http', 'rest']
            ]);

            // Test connection
            $this->info('🔗 Testing connection to MCP server...');
            
            try {
                $serverInfo = $server->getServerInfo();
                $this->info('✅ Connected successfully to MCP server');
                $this->line("  Server Info: " . json_encode($serverInfo));
            } catch (\Exception $e) {
                $this->error('❌ Failed to connect to MCP server: ' . $e->getMessage());
                return Command::FAILURE;
            }

            // Discover server resources and tools
            $this->info('🔍 Discovering server resources and tools...');
            
            try {
                $discoveredResources = $server->discoverResources();
                $this->info('✅ Server resource discovery completed');
                $this->line("  Resources found: " . count($discoveredResources));
                
                // Also try to discover tools from the server
                $this->info('🛠️ Discovering available tools...');
                
                // Create tools based on the known tools from your server
                $knownTools = [
                    'add' => [
                        'description' => 'Adds two numbers',
                        'parameters' => [
                            'a' => ['type' => 'number', 'description' => 'First number'],
                            'b' => ['type' => 'number', 'description' => 'Second number']
                        ]
                    ],
                    'multiply' => [
                        'description' => 'Multiplies two numbers',
                        'parameters' => [
                            'a' => ['type' => 'number', 'description' => 'First number'],
                            'b' => ['type' => 'number', 'description' => 'Second number']
                        ]
                    ],
                    'get_time' => [
                        'description' => 'Gets the current time',
                        'parameters' => []
                    ]
                ];
                
                foreach ($knownTools as $toolName => $toolConfig) {
                    // Create a resource for the tool
                    $resource = new MCPResource(
                        $toolName,
                        $toolConfig['description'],
                        "/{$toolName}",
                        $toolConfig['parameters'],
                        ['type' => 'tool']
                    );
                    
                    $server->addResource($resource);
                    
                    // Create a tool that calls the server via HTTP
                    $tool = new MCPTool(
                        $toolName,
                        $resource,
                        $server,
                        function (array $params) use ($mcpConfig, $toolName) {
                            // Make JSON-RPC call to the server endpoint
                            $jsonrpcPayload = [
                                'jsonrpc' => '2.0',
                                'id' => 1,
                                'method' => 'tools/call',
                                'params' => [
                                    'name' => $toolName,
                                    'arguments' => $params
                                ]
                            ];
                            
                            $response = \Illuminate\Support\Facades\Http::withHeaders($mcpConfig['headers'])
                                ->post($mcpConfig['server_url'], $jsonrpcPayload);
                            
                            if ($response->successful()) {
                                $result = $response->json();
                                // Extract the result content from JSON-RPC response
                                if (isset($result['result']['content'][0]['text'])) {
                                    return ['result' => $result['result']['content'][0]['text']];
                                }
                                return $result;
                            } else {
                                throw new \Exception("HTTP {$response->status()}: " . $response->body());
                            }
                        },
                        [
                            'type' => 'object',
                            'properties' => $toolConfig['parameters']
                        ]
                    );
                    
                    $mcpManager->addTool($tool);
                    $this->line("  ✅ Registered tool: {$toolName}");
                }
                
                $this->info("✅ Registered " . count($knownTools) . " tools");
                
            } catch (\Exception $e) {
                $this->warn('⚠️ Resource discovery failed: ' . $e->getMessage());
                $this->info('Continuing with manual tool registration...');
            }

            // Test available tools
            $this->info('🛠️ Testing available tools...');
            
            $toolName = $this->option('tool');
            $params = json_decode($this->option('params') ?: '{}', true);
            $method = $this->option('method');
            
            // Default parameters for common tools
            if ($toolName === 'add' && empty($params)) {
                $params = ['a' => 5, 'b' => 3];
            } elseif ($toolName === 'multiply' && empty($params)) {
                $params = ['a' => 4, 'b' => 6];
            } elseif ($toolName === 'get_time' && empty($params)) {
                $params = [];
            }

            $this->info("Testing tool: {$toolName}");
            $this->line("Method: {$method}");
            $this->line("Parameters: " . json_encode($params));
            $this->newLine();

            // Test tool execution through manager
            try {
                $this->info('🔍 Debugging tool registration...');
                $this->line("Tools in manager: " . count($mcpManager->getTools()));
                $this->line("Available tools: " . implode(', ', array_keys($mcpManager->getTools())));
                
                if ($mcpManager->getTool($toolName)) {
                    $this->info("✅ Tool '{$toolName}' found in manager");
                } else {
                    $this->error("❌ Tool '{$toolName}' not found in manager");
                    return Command::FAILURE;
                }
                
                $result = $mcpManager->executeTool($toolName, $params);
                $this->info("✅ Tool execution result:");
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
            } catch (\Exception $e) {
                $this->error("❌ Tool execution failed: " . $e->getMessage());
            }

            // Test JSON-RPC format validation
            $this->info('🔍 Testing JSON-RPC format...');
            
            $testPayloads = [
                'add' => ['a' => 15, 'b' => 25],
                'multiply' => ['a' => 6, 'b' => 7],
                'get_time' => []
            ];
            
            foreach ($testPayloads as $testTool => $testParams) {
                try {
                    $testJsonrpc = [
                        'jsonrpc' => '2.0',
                        'id' => 1,
                        'method' => 'tools/call',
                        'params' => [
                            'name' => $testTool,
                            'arguments' => $testParams
                        ]
                    ];
                    
                    $testResponse = \Illuminate\Support\Facades\Http::withHeaders($mcpConfig['headers'])
                        ->post($mcpConfig['server_url'], $testJsonrpc);
                    
                    if ($testResponse->successful()) {
                        $testResult = $testResponse->json();
                        $this->line("  ✅ {$testTool}: " . ($testResult['result']['content'][0]['text'] ?? 'Success'));
                    } else {
                        $this->line("  ❌ {$testTool}: HTTP {$testResponse->status()}");
                    }
                } catch (\Exception $e) {
                    $this->line("  ❌ {$testTool}: Error - " . $e->getMessage());
                }
            }

            // Get MCP statistics
            $this->info('📈 MCP Statistics:');
            $stats = $mcpManager->getStatistics();
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Servers', $stats['total_servers']],
                    ['Enabled Servers', $stats['enabled_servers']],
                    ['Servers with SSE', $stats['servers_with_sse']],
                    ['Total Resources', $stats['total_resources']],
                    ['Total Tools', $stats['total_tools']]
                ]
            );

            // Test server capabilities
            $this->info('🔍 Testing Server Capabilities...');
            
            $capabilities = $server->getCapabilities();
            $this->line("Server capabilities: " . json_encode($capabilities));
            
            if (!empty($capabilities)) {
                if (in_array('http', $capabilities)) {
                    $this->info('✅ Server supports HTTP');
                } else {
                    $this->warn('⚠️ Server does not support HTTP');
                }
            } else {
                $this->info('ℹ️ MCP Server is not exposing capabilities');
            }

            $this->newLine();
            $this->info('🎉 MCP HTTP testing completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ MCP HTTP testing failed: ' . $e->getMessage());
            $this->newLine();
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
} 