<?php

/**
 * MCPSSEExample - Model Context Protocol Server-Sent Events Example
 *
 * Purpose: Demonstrates MCP (Model Context Protocol) SSE (Server-Sent Events)
 * functionality with realistic streaming examples. This command shows how to
 * implement real-time data streaming through MCP using different data types.
 *
 * SSE Concept: Server-Sent Events enable real-time streaming of data from
 * MCP servers to clients, allowing for continuous updates and live data feeds
 * such as stock prices, log analysis, and sensor data.
 *
 * Features Tested:
 * - Real-time data streaming with SSE
 * - Different streaming data types (stock data, logs, sensors)
 * - MCP server setup with streaming capabilities
 * - Resource and tool registration for streaming
 * - Duration and frequency control
 * - Simulated streaming data generation
 *
 * Usage:
 * - Stock data: php artisan agent:mcp-sse-example --type=stock-data --duration=10
 * - Log analysis: php artisan agent:mcp-sse-example --type=log-analysis --duration=15
 * - Sensor data: php artisan agent:mcp-sse-example --type=sensor-data --duration=20
 * - Custom duration: php artisan agent:mcp-sse-example --duration=30
 *
 * Test Scenarios:
 * 1. Stock data streaming with real-time updates
 * 2. Log analysis streaming with pattern matching
 * 3. Sensor data streaming with multiple sensors
 * 4. Duration and frequency control
 * 5. Simulated streaming data generation
 *
 * Streaming Data Types:
 * - stock-data: Real-time stock price updates
 * - log-analysis: Real-time log analysis and filtering
 * - sensor-data: Real-time sensor readings and monitoring
 *
 * Available Tools:
 * - get_stock_data: Stream real-time stock prices
 * - analyze_logs: Stream log analysis results
 * - get_sensor_data: Stream sensor readings
 *
 * Streaming Features:
 * - Real-time data updates
 * - Configurable update intervals
 * - Multiple data sources
 * - Pattern matching and filtering
 * - Duration and frequency control
 *
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

class MCPSSEExample extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:mcp-sse-example {--type=stock-data} {--duration=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrate MCP SSE functionality with realistic streaming examples';

    protected $mcpManager;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 MCP SSE Example - Realistic Streaming Demo');
        $this->newLine();

        $type = $this->option('type');
        $duration = (int) $this->option('duration');

        try {
            // Create MCP Manager
            $mcpManager = new MCPManager([
                'enable_logging' => true,
                'timeout' => 30,
                'max_retries' => 3
            ]);
            $this->mcpManager = $mcpManager;

            // Create simulated MCP server
            $serverName = 'sse-demo-server';
            $serverUrl = 'http://localhost:8080';

            $this->info("📡 Setting up SSE demo server: {$serverName}");
            
            // Create MCP Client with SSE support
            $client = new MCPClient($serverUrl, [
                'Authorization' => 'Bearer demo-token',
                'Content-Type' => 'application/json'
            ], 30, 3, true);

            // Create MCP Server
            $server = new MCPServer($serverName, $serverUrl, [
                'enabled' => true,
                'timeout' => 30,
                'max_retries' => 3,
                'headers' => [
                    'Authorization' => 'Bearer demo-token',
                    'Content-Type' => 'application/json'
                ]
            ]);

            // Add server to manager
            $mcpManager->addServer($serverName, $serverUrl, [
                'enabled' => true,
                'capabilities' => ['sse', 'streaming']
            ]);

            // Create different types of streaming resources based on type
            switch ($type) {
                case 'stock-data':
                    $this->demoStockDataStreaming($server, $duration);
                    break;
                case 'log-analysis':
                    $this->demoLogAnalysisStreaming($server, $duration);
                    break;
                case 'sensor-data':
                    $this->demoSensorDataStreaming($server, $duration);
                    break;
                default:
                    $this->demoStockDataStreaming($server, $duration);
            }

            $this->newLine();
            $this->info('🎉 MCP SSE example completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ MCP SSE example failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Demo stock data streaming.
     */
    private function demoStockDataStreaming(MCPServer $server, int $duration): void
    {
        $this->info('📈 Stock Data Streaming Demo');
        $this->newLine();

        // Create stock data resource
        $resource = new MCPResource(
            'stock_prices',
            'Real-time stock price streaming',
            '/stock_prices',
            [
                'symbols' => [
                    'type' => 'array',
                    'description' => 'Stock symbols to monitor',
                    'default' => ['AAPL', 'GOOGL', 'MSFT']
                ],
                'interval' => [
                    'type' => 'integer',
                    'description' => 'Update interval in seconds',
                    'default' => 2
                ]
            ],
            ['type' => 'streaming']
        );

        $server->addResource($resource);

        // Create stock data tool
        $stockTool = new MCPTool(
            'get_stock_data',
            $resource,
            $server,
            function (array $params) use ($duration) {
                $symbols = $params['symbols'] ?? ['AAPL', 'GOOGL', 'MSFT'];
                $interval = $params['interval'] ?? 2;
                
                $data = [];
                $startTime = time();
                
                while ((time() - $startTime) < $duration) {
                    $chunk = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'symbols' => []
                    ];
                    
                    foreach ($symbols as $symbol) {
                        $chunk['symbols'][] = [
                            'symbol' => $symbol,
                            'price' => round(rand(100, 500) + (rand(0, 100) / 100), 2),
                            'change' => round((rand(-50, 50) / 100), 2),
                            'volume' => rand(1000000, 10000000)
                        ];
                    }
                    
                    $data[] = $chunk;
                    sleep($interval);
                }
                
                return $data;
            },
            [
                'type' => 'object',
                'properties' => [
                    'symbols' => [
                        'type' => 'array',
                        'description' => 'Stock symbols to monitor'
                    ],
                    'interval' => [
                        'type' => 'integer',
                        'description' => 'Update interval in seconds'
                    ]
                ]
            ]
        );

        // Agregar tool al manager en vez del server
        $this->mcpManager->addTool($stockTool);

        // Stream stock data
        $this->info('📊 Streaming stock data...');
        $this->newLine();

        $chunkCount = 0;
        $startTime = time();
        
        // Simulate streaming instead of using streamResource
        while ((time() - $startTime) < $duration) {
            $chunkCount++;
            $chunk = [
                'timestamp' => date('Y-m-d H:i:s'),
                'symbols' => []
            ];
            
            foreach (['AAPL', 'GOOGL'] as $symbol) {
                $chunk['symbols'][] = [
                    'symbol' => $symbol,
                    'price' => round(rand(100, 500) + (rand(0, 100) / 100), 2),
                    'change' => round((rand(-50, 50) / 100), 2),
                    'volume' => rand(1000000, 10000000)
                ];
            }
            
            $this->line("📈 Update {$chunkCount}: " . json_encode($chunk));
            sleep(1);
        }

        $this->newLine();
        $this->info("✅ Streamed {$chunkCount} stock updates");
    }

    /**
     * Demo log analysis streaming.
     */
    private function demoLogAnalysisStreaming(MCPServer $server, int $duration): void
    {
        $this->info('📋 Log Analysis Streaming Demo');
        $this->newLine();

        // Create log analysis resource
        $resource = new MCPResource(
            'log_analysis',
            'Real-time log analysis streaming',
            '/log_analysis',
            [
                'log_level' => [
                    'type' => 'string',
                    'description' => 'Log level to filter',
                    'default' => 'INFO'
                ],
                'pattern' => [
                    'type' => 'string',
                    'description' => 'Search pattern',
                    'default' => 'error'
                ]
            ],
            ['type' => 'streaming']
        );

        $server->addResource($resource);

        // Create log analysis tool
        $logTool = new MCPTool(
            'analyze_logs',
            $resource,
            $server,
            function (array $params) use ($duration) {
                $logLevel = $params['log_level'] ?? 'INFO';
                $pattern = $params['pattern'] ?? 'error';
                
                $logLevels = ['DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'];
                $logMessages = [
                    'User authentication successful',
                    'Database connection established',
                    'API request processed',
                    'Cache miss detected',
                    'Memory usage high',
                    'Network timeout occurred',
                    'File upload completed',
                    'Background job started'
                ];
                
                $data = [];
                $startTime = time();
                
                while ((time() - $startTime) < $duration) {
                    $chunk = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'level' => $logLevels[array_rand($logLevels)],
                        'message' => $logMessages[array_rand($logMessages)],
                        'source' => 'app.log',
                        'line' => rand(1, 1000),
                        'matches_pattern' => rand(0, 1) === 1
                    ];
                    
                    $data[] = $chunk;
                    sleep(1);
                }
                
                return $data;
            },
            [
                'type' => 'object',
                'properties' => [
                    'log_level' => [
                        'type' => 'string',
                        'description' => 'Log level to filter'
                    ],
                    'pattern' => [
                        'type' => 'string',
                        'description' => 'Search pattern'
                    ]
                ]
            ]
        );

        // Agregar tool al manager en vez del server
        $this->mcpManager->addTool($logTool);

        // Stream log analysis
        $this->info('📊 Streaming log analysis...');
        $this->newLine();

        $chunkCount = 0;
        $startTime = time();
        
        foreach ($server->streamResource('log_analysis', ['log_level' => 'ERROR', 'pattern' => 'timeout']) as $chunk) {
            $chunkCount++;
            $this->line("📋 Log {$chunkCount}: " . json_encode($chunk));
            
            if ((time() - $startTime) >= $duration) {
                break;
            }
        }

        $this->newLine();
        $this->info("✅ Analyzed {$chunkCount} log entries");
    }

    /**
     * Demo sensor data streaming.
     */
    private function demoSensorDataStreaming(MCPServer $server, int $duration): void
    {
        $this->info('🌡️ Sensor Data Streaming Demo');
        $this->newLine();

        // Create sensor data resource
        $resource = new MCPResource(
            'sensor_data',
            'Real-time sensor data streaming',
            '/sensor_data',
            [
                'sensors' => [
                    'type' => 'array',
                    'description' => 'Sensor IDs to monitor',
                    'default' => ['temp_01', 'humidity_01', 'pressure_01']
                ],
                'frequency' => [
                    'type' => 'integer',
                    'description' => 'Reading frequency in seconds',
                    'default' => 1
                ]
            ],
            ['type' => 'streaming']
        );

        $server->addResource($resource);

        // Create sensor data tool
        $sensorTool = new MCPTool(
            'get_sensor_data',
            $resource,
            $server,
            function (array $params) use ($duration) {
                $sensors = $params['sensors'] ?? ['temp_01', 'humidity_01', 'pressure_01'];
                $frequency = $params['frequency'] ?? 1;
                
                $data = [];
                $startTime = time();
                
                while ((time() - $startTime) < $duration) {
                    $chunk = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'sensors' => []
                    ];
                    
                    foreach ($sensors as $sensor) {
                        $chunk['sensors'][] = [
                            'id' => $sensor,
                            'value' => round(rand(2000, 3000) / 100, 2),
                            'unit' => $this->getSensorUnit($sensor),
                            'status' => rand(0, 1) === 1 ? 'normal' : 'warning'
                        ];
                    }
                    
                    $data[] = $chunk;
                    sleep($frequency);
                }
                
                return $data;
            },
            [
                'type' => 'object',
                'properties' => [
                    'sensors' => [
                        'type' => 'array',
                        'description' => 'Sensor IDs to monitor'
                    ],
                    'frequency' => [
                        'type' => 'integer',
                        'description' => 'Reading frequency in seconds'
                    ]
                ]
            ]
        );

        // Agregar tool al manager en vez del server
        $this->mcpManager->addTool($sensorTool);

        // Stream sensor data
        $this->info('📊 Streaming sensor data...');
        $this->newLine();

        $chunkCount = 0;
        $startTime = time();
        
        foreach ($server->streamResource('sensor_data', ['sensors' => ['temp_01', 'humidity_01'], 'frequency' => 1]) as $chunk) {
            $chunkCount++;
            $this->line("🌡️ Reading {$chunkCount}: " . json_encode($chunk));
            
            if ((time() - $startTime) >= $duration) {
                break;
            }
        }

        $this->newLine();
        $this->info("✅ Streamed {$chunkCount} sensor readings");
    }

    /**
     * Get sensor unit based on sensor ID.
     */
    private function getSensorUnit(string $sensorId): string
    {
        if (str_contains($sensorId, 'temp')) {
            return '°C';
        } elseif (str_contains($sensorId, 'humidity')) {
            return '%';
        } elseif (str_contains($sensorId, 'pressure')) {
            return 'hPa';
        }
        
        return 'units';
    }
} 