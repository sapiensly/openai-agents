<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

use Illuminate\Support\Facades\Log;

/**
 * Class MCPSTDIOClient
 *
 * Handles communication with MCP servers using STDIO (stdin/stdout).
 * This is useful for local tools, CLI applications, and scripts.
 */
class MCPSTDIOClient
{
    /**
     * The process command to execute
     */
    private string $command;

    /**
     * The process arguments
     */
    private array $arguments;

    /**
     * The working directory
     */
    private string $workingDirectory;

    /**
     * Environment variables
     */
    private array $environment;

    /**
     * Request timeout in seconds
     */
    private int $timeout;

    /**
     * Whether to enable logging
     */
    private bool $enableLogging;

    /**
     * The process resource
     */
    private $process = null;

    /**
     * Process pipes
     */
    private array $pipes = [];

    /**
     * Create a new MCPSTDIOClient instance.
     *
     * @param string $command The command to execute
     * @param array $arguments Command arguments
     * @param string $workingDirectory Working directory
     * @param array $environment Environment variables
     * @param int $timeout Request timeout
     * @param bool $enableLogging Whether to enable logging
     */
    public function __construct(
        string $command,
        array $arguments = [],
        string $workingDirectory = '',
        array $environment = [],
        int $timeout = 30,
        bool $enableLogging = true
    ) {
        $this->command = $command;
        $this->arguments = $arguments;
        $this->workingDirectory = $workingDirectory ?: getcwd();
        $this->environment = array_merge($_ENV, $environment);
        $this->timeout = $timeout;
        $this->enableLogging = $enableLogging;
    }

    /**
     * Get the command.
     *
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get the arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get the working directory.
     *
     * @return string
     */
    public function getWorkingDirectory(): string
    {
        return $this->workingDirectory;
    }

    /**
     * Get the environment variables.
     *
     * @return array
     */
    public function getEnvironment(): array
    {
        return $this->environment;
    }

    /**
     * Test connection to the MCP server.
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $this->startProcess();
            
            // Send a simple ping request
            $pingRequest = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [],
                    'clientInfo' => [
                        'name' => 'openai-agents-php',
                        'version' => '1.0.0'
                    ]
                ]
            ];

            $response = $this->sendRequest($pingRequest);
            $this->stopProcess();

            if ($this->enableLogging) {
                Log::info('MCP STDIO connection test', [
                    'command' => $this->command,
                    'success' => !empty($response)
                ]);
            }

            return !empty($response);
        } catch (\Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP STDIO connection test failed', [
                    'command' => $this->command,
                    'error' => $e->getMessage()
                ]);
            }
            $this->stopProcess();
            return false;
        }
    }

    /**
     * Send a JSON-RPC request to the MCP server.
     *
     * @param array $request The JSON-RPC request
     * @return array|null
     */
    public function sendRequest(array $request): ?array
    {
        try {
            $this->startProcess();

            // Send the request
            $jsonRequest = json_encode($request) . "\n";
            fwrite($this->pipes[0], $jsonRequest);

            // Read the response
            $response = $this->readResponse();

            if ($this->enableLogging) {
                Log::info('MCP STDIO request sent', [
                    'command' => $this->command,
                    'method' => $request['method'] ?? 'unknown',
                    'has_response' => !empty($response)
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            if ($this->enableLogging) {
                Log::error('MCP STDIO request failed', [
                    'command' => $this->command,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

    /**
     * Call a resource on the MCP server.
     *
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @return array
     */
    public function callResource(string $resourceName, array $parameters = []): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => 'resources/call',
            'params' => [
                'name' => $resourceName,
                'arguments' => $parameters
            ]
        ];

        $response = $this->sendRequest($request);

        if ($this->enableLogging) {
            Log::info('MCP STDIO resource call', [
                'command' => $this->command,
                'resource' => $resourceName,
                'parameters' => $parameters,
                'success' => !empty($response)
            ]);
        }

        return $response ?? [];
    }

    /**
     * List available resources.
     *
     * @return array
     */
    public function listResources(): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => 'resources/list',
            'params' => []
        ];

        $response = $this->sendRequest($request);

        if ($this->enableLogging) {
            Log::info('MCP STDIO resource list', [
                'command' => $this->command,
                'resources_count' => count($response['result']['resources'] ?? [])
            ]);
        }

        return $response['result']['resources'] ?? [];
    }

    /**
     * Get server information.
     *
     * @return array
     */
    public function getServerInfo(): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => 'serverInfo',
            'params' => []
        ];

        $response = $this->sendRequest($request);

        if ($this->enableLogging) {
            Log::info('MCP STDIO server info', [
                'command' => $this->command,
                'has_info' => !empty($response)
            ]);
        }

        return $response['result'] ?? [];
    }

    /**
     * Start the process.
     *
     * @return void
     */
    private function startProcess(): void
    {
        if ($this->process !== null) {
            return; // Process already started
        }

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $commandLine = $this->command;
        if (!empty($this->arguments)) {
            $commandLine .= ' ' . implode(' ', array_map('escapeshellarg', $this->arguments));
        }

        $this->process = proc_open($commandLine, $descriptors, $this->pipes, $this->workingDirectory, $this->environment);

        if ($this->process === false) {
            throw new \Exception("Failed to start process: {$commandLine}");
        }

        // Set non-blocking mode for stdout
        stream_set_blocking($this->pipes[1], false);
    }

    /**
     * Read response from the process.
     *
     * @return array|null
     */
    private function readResponse(): ?array
    {
        $response = '';
        $startTime = time();

        while (time() - $startTime < $this->timeout) {
            $data = fgets($this->pipes[1]);
            
            if ($data === false) {
                // No data available, wait a bit
                usleep(10000); // 10ms
                continue;
            }

            $response .= $data;

            // Check if we have a complete JSON response
            $decoded = json_decode($response, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        throw new \Exception('Timeout waiting for response from STDIO process');
    }

    /**
     * Stop the process.
     *
     * @return void
     */
    private function stopProcess(): void
    {
        if ($this->process === null) {
            return;
        }

        // Close pipes
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        // Terminate process
        $status = proc_get_status($this->process);
        if ($status['running']) {
            proc_terminate($this->process);
            
            // Wait for termination
            $timeout = 5;
            $startTime = time();
            while (time() - $startTime < $timeout) {
                $status = proc_get_status($this->process);
                if (!$status['running']) {
                    break;
                }
                usleep(100000); // 100ms
            }
            
            // Force kill if still running
            if ($status['running']) {
                proc_kill($this->process, SIGKILL);
            }
        }

        proc_close($this->process);
        $this->process = null;
        $this->pipes = [];
    }

    /**
     * Get process status.
     *
     * @return array
     */
    public function getProcessStatus(): array
    {
        if ($this->process === null) {
            return ['running' => false];
        }

        return proc_get_status($this->process);
    }

    /**
     * Check if the process is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        $status = $this->getProcessStatus();
        return $status['running'] ?? false;
    }

    /**
     * Get the process ID.
     *
     * @return int|null
     */
    public function getProcessId(): ?int
    {
        $status = $this->getProcessStatus();
        return $status['pid'] ?? null;
    }

    /**
     * Destructor to ensure process is cleaned up.
     */
    public function __destruct()
    {
        $this->stopProcess();
    }

    /**
     * Convert the client to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'arguments' => $this->arguments,
            'working_directory' => $this->workingDirectory,
            'environment' => $this->environment,
            'timeout' => $this->timeout,
            'enable_logging' => $this->enableLogging,
            'transport' => 'stdio',
            'is_running' => $this->isRunning(),
            'process_id' => $this->getProcessId(),
        ];
    }
} 