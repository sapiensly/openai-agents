<?php

/**
 * TestMCPSTDIO - Model Context Protocol STDIO Testing
 *
 * Purpose: Tests MCP (Model Context Protocol) server functionality with STDIO
 * transport. This command validates MCP client-server communication, resource
 * discovery, and tool integration over STDIO connections using system commands.
 *
 * STDIO Transport Concept: MCP servers can communicate via standard input/output
 * streams, allowing integration with command-line tools and system utilities.
 * This enables AI models to interact with local system resources and tools.
 *
 * Features Tested:
 * - STDIO-based MCP server connection
 * - Command and argument management
 * - Resource discovery and listing
 * - Process information and status
 * - Server statistics and debugging
 * - Working directory and timeout management
 *
 * Usage:
 * - Basic test: php artisan agent:test-mcp-stdio
 * - Custom command: php artisan agent:test-mcp-stdio --command=echo --args='["Hello World"]'
 * - Git version: php artisan agent:test-mcp-stdio --command=git --args='["--version"]'
 * - List commands: php artisan agent:test-mcp-stdio --list-commands
 * - Custom timeout: php artisan agent:test-mcp-stdio --timeout=60
 * - Working directory: php artisan agent:test-mcp-stdio --working-dir=/path/to/dir
 *
 * Test Scenarios:
 * 1. STDIO-based MCP server connection
 * 2. Command and argument management
 * 3. Resource discovery and listing
 * 4. Process information and status
 * 5. Server statistics and debugging
 * 6. Working directory and timeout management
 *
 * Available Test Commands:
 * - echo: Simple echo command
 * - git: Git version command
 * - docker: Docker version command
 * - php: PHP version command
 * - ls: List directory contents
 * - pwd: Print working directory
 *
 * STDIO Configuration:
 * - Command execution and management
 * - Argument passing and validation
 * - Working directory specification
 * - Timeout and error handling
 * - Process monitoring and status
 *
 */

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Console\Commands;

use Illuminate\Console\Command;
use Sapiensly\OpenaiAgents\MCP\MCPManager;
use Sapiensly\OpenaiAgents\MCP\MCPServer;
use Sapiensly\OpenaiAgents\MCP\MCPSTDIOClient;

class TestMCPSTDIO extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:test-mcp-stdio 
                            {--command=echo : The command to test}
                            {--args= : Command arguments (JSON array)}
                            {--working-dir= : Working directory}
                            {--timeout=30 : Request timeout}
                            {--list-commands : List available test commands}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MCP server with STDIO transport';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Testing MCP Server with STDIO Transport');
        $this->newLine();

        // Check if MCP is enabled
        if (!config('agents.mcp.enabled')) {
            $this->error('❌ MCP is not enabled. Set MCP_ENABLED=true in your .env file');
            return Command::FAILURE;
        }

        // List available test commands
        if ($this->option('list-commands')) {
            $this->showAvailableCommands();
            return Command::SUCCESS;
        }

        try {
            $command = $this->option('command');
            $args = $this->option('args') ? json_decode($this->option('args'), true) : [];
            $workingDir = $this->option('working-dir') ?: getcwd();
            $timeout = (int) $this->option('timeout');

            $this->info('📡 STDIO Configuration:');
            $this->line("  Command: {$command}");
            $this->line("  Arguments: " . json_encode($args));
            $this->line("  Working Directory: {$workingDir}");
            $this->line("  Timeout: {$timeout}s");
            $this->newLine();

            // Create MCP STDIO Client
            $stdioClient = new MCPSTDIOClient(
                $command,
                $args,
                $workingDir,
                [],
                $timeout,
                true
            );

            // Test connection
            $this->info('🔗 Testing connection...');
            if ($stdioClient->testConnection()) {
                $this->info('✅ Connection successful');
            } else {
                $this->error('❌ Connection failed');
                return Command::FAILURE;
            }

            // Get server info
            $this->info('📋 Getting server information...');
            try {
                $serverInfo = $stdioClient->getServerInfo();
                $this->line("Server info: " . json_encode($serverInfo, JSON_PRETTY_PRINT));
            } catch (\Exception $e) {
                $this->warn("⚠️ Could not get server info: " . $e->getMessage());
            }

            // List resources
            $this->info('📚 Listing available resources...');
            try {
                $resources = $stdioClient->listResources();
                if (!empty($resources)) {
                    $this->table(
                        ['Name', 'Description', 'URI'],
                        array_map(function($resource) {
                            return [
                                $resource['name'] ?? 'Unknown',
                                $resource['description'] ?? 'No description',
                                $resource['uri'] ?? 'No URI'
                            ];
                        }, $resources)
                    );
                } else {
                    $this->line('No resources found');
                }
            } catch (\Exception $e) {
                $this->warn("⚠️ Could not list resources: " . $e->getMessage());
            }

            // Test process information
            $this->info('🔍 Process Information:');
            $this->line("  Process ID: " . ($stdioClient->getProcessId() ?? 'N/A'));
            $this->line("  Is Running: " . ($stdioClient->isRunning() ? 'Yes' : 'No'));
            
            $status = $stdioClient->getProcessStatus();
            $this->line("  Status: " . json_encode($status));

            // Test with MCP Manager
            $this->info('🔧 Testing with MCP Manager...');
            
            $mcpManager = new MCPManager([
                'enable_logging' => true,
                'timeout' => $timeout,
                'max_retries' => 3
            ]);

            // Create MCP Server with STDIO transport
            $server = new MCPServer('stdio-test-server', $command, [
                'transport' => 'stdio',
                'command' => $command,
                'arguments' => $args,
                'working_directory' => $workingDir,
                'timeout' => $timeout,
                'enabled' => true
            ]);

            // Add server to manager
            $mcpManager->addServer('stdio-test', $command, [
                'transport' => 'stdio',
                'command' => $command,
                'arguments' => $args,
                'working_directory' => $workingDir,
                'timeout' => $timeout,
                'enabled' => true
            ]);

            // Test server connection
            if ($server->testConnection()) {
                $this->info('✅ Server connection successful');
            } else {
                $this->error('❌ Server connection failed');
            }

            // Get server info from manager
            $serverInfo = $server->getServerInfo();
            $this->info('📊 Server Information:');
            $this->line("  Name: {$serverInfo['name']}");
            $this->line("  Transport: {$serverInfo['transport']}");
            $this->line("  Enabled: " . ($serverInfo['enabled'] ? 'Yes' : 'No'));
            $this->line("  Resources: {$serverInfo['resources_count']}");

            // Get server stats
            $stats = $server->getServerStats();
            $this->info('📈 Server Statistics:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Name', $stats['name']],
                    ['Transport', $stats['transport']],
                    ['Enabled', $stats['enabled'] ? 'Yes' : 'No'],
                    ['Resources Count', $stats['resources_count']],
                    ['Process ID', $stats['process_id'] ?? 'N/A'],
                    ['Is Running', $stats['is_running'] ? 'Yes' : 'No'],
                ]
            );

            // Test resource discovery
            $this->info('🔍 Testing resource discovery...');
            $discoveredResources = $server->discoverResources();
            $this->line("Discovered resources: " . count($discoveredResources));

            $this->newLine();
            $this->info('🎉 MCP STDIO testing completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ MCP STDIO testing failed: ' . $e->getMessage());
            $this->newLine();
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }

    /**
     * Show available test commands.
     */
    private function showAvailableCommands(): void
    {
        $this->info('📋 Available Test Commands:');
        $this->newLine();

        $commands = [
            'echo' => [
                'description' => 'Simple echo command',
                'args' => '["Hello World"]',
                'example' => 'php artisan agent:test-mcp-stdio --command=echo --args=\'["Hello World"]\''
            ],
            'git' => [
                'description' => 'Git version command',
                'args' => '["--version"]',
                'example' => 'php artisan agent:test-mcp-stdio --command=git --args=\'["--version"]\''
            ],
            'docker' => [
                'description' => 'Docker version command',
                'args' => '["--version"]',
                'example' => 'php artisan agent:test-mcp-stdio --command=docker --args=\'["--version"]\''
            ],
            'php' => [
                'description' => 'PHP version command',
                'args' => '["--version"]',
                'example' => 'php artisan agent:test-mcp-stdio --command=php --args=\'["--version"]\''
            ],
            'ls' => [
                'description' => 'List directory contents',
                'args' => '["-la"]',
                'example' => 'php artisan agent:test-mcp-stdio --command=ls --args=\'["-la"]\''
            ],
            'pwd' => [
                'description' => 'Print working directory',
                'args' => '[]',
                'example' => 'php artisan agent:test-mcp-stdio --command=pwd --args=\'[]\''
            ],
        ];

        foreach ($commands as $command => $info) {
            $this->line("🔹 <fg=green>{$command}</fg=green>");
            $this->line("   Description: {$info['description']}");
            $this->line("   Arguments: {$info['args']}");
            $this->line("   Example: {$info['example']}");
            $this->newLine();
        }

        $this->info('💡 Tips:');
        $this->line('  • Use --working-dir to set a specific working directory');
        $this->line('  • Use --timeout to adjust the request timeout');
        $this->line('  • Commands that output JSON are more suitable for MCP testing');
        $this->line('  • Some commands may not respond to JSON-RPC requests');
    }
} 