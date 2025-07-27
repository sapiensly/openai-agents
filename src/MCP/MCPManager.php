<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Class MCPManager
 *
 * Orchestrates MCP functionality and manages servers, resources, and tools.
 */
class MCPManager
{
    /**
     * Registered MCP servers
     */
    private array $servers = [];

    /**
     * Registered MCP resources
     */
    private array $resources = [];

    /**
     * Registered MCP tools
     */
    private array $tools = [];

    /**
     * Whether MCP is enabled
     */
    private bool $enabled = true;

    /**
     * Manager configuration
     */
    private array $config = [];

    /**
     * Manager statistics
     */
    private array $stats = [
        'total_servers' => 0,
        'enabled_servers' => 0,
        'total_resources' => 0,
        'enabled_resources' => 0,
        'total_tools' => 0,
        'enabled_tools' => 0,
        'total_calls' => 0,
        'successful_calls' => 0,
        'failed_calls' => 0,
    ];

    /**
     * Create a new MCPManager instance.
     *
     * @param array $config Manager configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enable_logging' => true,
            'auto_discover' => true,
            'connection_timeout' => 30,
            'max_retries' => 3,
        ], $config);
    }

    /**
     * Add an MCP server.
     *
     * @param string $name The server name
     * @param string $url The server URL
     * @param array $config Server configuration
     * @return self
     */
    public function addServer(string $name, string $url, array $config = []): self
    {
        $server = new MCPServer($name, $url, $config);
        $this->servers[$name] = $server;

        // Auto-discover resources if enabled
        if ($this->config['auto_discover']) {
            $this->discoverServerResources($name);
        }

        $this->updateStats();
        return $this;
    }

    /**
     * Get an MCP server.
     *
     * @param string $name The server name
     * @return MCPServer|null
     */
    public function getServer(string $name): ?MCPServer
    {
        return $this->servers[$name] ?? null;
    }

    /**
     * Remove an MCP server.
     *
     * @param string $name The server name
     * @return self
     */
    public function removeServer(string $name): self
    {
        if (isset($this->servers[$name])) {
            // Remove associated tools
            $this->tools = array_filter($this->tools, function (MCPTool $tool) use ($name) {
                return $tool->getServer()->getName() !== $name;
            });

            unset($this->servers[$name]);
            $this->updateStats();
        }

        return $this;
    }

    /**
     * Get all servers.
     *
     * @return array
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * Get enabled servers.
     *
     * @return array
     */
    public function getEnabledServers(): array
    {
        return array_filter($this->servers, function (MCPServer $server) {
            return $server->isEnabled();
        });
    }

    /**
     * Add a resource to a server.
     *
     * @param string $serverName The server name
     * @param MCPResource $resource The resource to add
     * @return self
     */
    public function addResource(string $serverName, MCPResource $resource): self
    {
        $server = $this->getServer($serverName);
        if (!$server) {
            throw new Exception("Server '{$serverName}' not found");
        }

        $server->addResource($resource);
        $this->resources[$resource->getName()] = $resource;
        $this->updateStats();

        return $this;
    }

    /**
     * Get a resource.
     *
     * @param string $name The resource name
     * @return MCPResource|null
     */
    public function getResource(string $name): ?MCPResource
    {
        return $this->resources[$name] ?? null;
    }

    /**
     * Get all resources.
     *
     * @return array
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Get enabled resources.
     *
     * @return array
     */
    public function getEnabledResources(): array
    {
        return array_filter($this->resources, function (MCPResource $resource) {
            return $resource->isEnabled();
        });
    }

    /**
     * Add an MCP tool.
     *
     * @param MCPTool $tool The tool to add
     * @return self
     */
    public function addTool(MCPTool $tool): self
    {
        $this->tools[$tool->getName()] = $tool;
        $this->updateStats();

        return $this;
    }

    /**
     * Get an MCP tool.
     *
     * @param string $name The tool name
     * @return MCPTool|null
     */
    public function getTool(string $name): ?MCPTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Get all tools.
     *
     * @return array
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Get enabled tools.
     *
     * @return array
     */
    public function getEnabledTools(): array
    {
        return array_filter($this->tools, function (MCPTool $tool) {
            return $tool->isEnabled();
        });
    }

    /**
     * Get tool definitions for agent registration.
     *
     * @return array
     */
    public function getToolDefinitions(): array
    {
        $definitions = [];
        foreach ($this->getEnabledTools() as $tool) {
            $definitions[] = $tool->getToolDefinition();
        }
        return $definitions;
    }

    /**
     * Execute a tool.
     *
     * @param string $toolName The tool name
     * @param array $parameters The tool parameters
     * @return mixed
     */
    public function executeTool(string $toolName, array $parameters = [])
    {
        $tool = $this->getTool($toolName);
        if (!$tool) {
            throw new Exception("Tool '{$toolName}' not found");
        }

        if (!$tool->isEnabled()) {
            throw new Exception("Tool '{$toolName}' is disabled");
        }

        $this->stats['total_calls']++;

        try {
            $result = $tool->execute($parameters);
            $this->stats['successful_calls']++;

            if ($this->config['enable_logging']) {
                Log::info('MCP tool executed successfully', [
                    'tool' => $toolName,
                    'parameters' => $parameters,
                    'result' => $result
                ]);
            }

            return $result;
        } catch (Exception $e) {
            $this->stats['failed_calls']++;

            if ($this->config['enable_logging']) {
                Log::error('MCP tool execution failed', [
                    'tool' => $toolName,
                    'parameters' => $parameters,
                    'error' => $e->getMessage()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Discover resources for a server.
     *
     * @param string $serverName The server name
     * @return array
     */
    public function discoverServerResources(string $serverName): array
    {
        $server = $this->getServer($serverName);
        if (!$server) {
            throw new Exception("Server '{$serverName}' not found");
        }

        $discoveredResources = $server->discoverResources();

        // Add discovered resources to the manager
        foreach ($server->getResources() as $resource) {
            $this->resources[$resource->getName()] = $resource;
        }

        $this->updateStats();

        return $discoveredResources;
    }

    /**
     * Test connection to all servers.
     *
     * @return array
     */
    public function testAllConnections(): array
    {
        $results = [];

        foreach ($this->servers as $server) {
            $results[$server->getName()] = [
                'enabled' => $server->isEnabled(),
                'connected' => $server->testConnection(),
                'url' => $server->getUrl(),
            ];
        }

        return $results;
    }

    /**
     * Get server information for all servers.
     *
     * @return array
     */
    public function getAllServerInfo(): array
    {
        $info = [];

        foreach ($this->servers as $server) {
            $info[$server->getName()] = $server->getServerInfo();
        }

        return $info;
    }

    /**
     * Get server statistics for all servers.
     *
     * @return array
     */
    public function getAllServerStats(): array
    {
        $stats = [];

        foreach ($this->servers as $server) {
            $stats[$server->getName()] = $server->getServerStats();
        }

        return $stats;
    }

    /**
     * Check if MCP is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set whether MCP is enabled.
     *
     * @param bool $enabled Whether MCP is enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get manager configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set manager configuration.
     *
     * @param array $config The configuration to set
     * @return self
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Get manager statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Update statistics.
     *
     * @return void
     */
    private function updateStats(): void
    {
        $this->stats['total_servers'] = count($this->servers);
        $this->stats['enabled_servers'] = count($this->getEnabledServers());
        $this->stats['total_resources'] = count($this->resources);
        $this->stats['enabled_resources'] = count($this->getEnabledResources());
        $this->stats['total_tools'] = count($this->tools);
        $this->stats['enabled_tools'] = count($this->getEnabledTools());
    }

    /**
     * Reset statistics.
     *
     * @return self
     */
    public function resetStats(): self
    {
        $this->stats = [
            'total_servers' => 0,
            'enabled_servers' => 0,
            'total_resources' => 0,
            'enabled_resources' => 0,
            'total_tools' => 0,
            'enabled_tools' => 0,
            'total_calls' => 0,
            'successful_calls' => 0,
            'failed_calls' => 0,
        ];
        $this->updateStats();
        return $this;
    }

    /**
     * Get manager information.
     *
     * @return array
     */
    public function getInfo(): array
    {
        return [
            'enabled' => $this->enabled,
            'config' => $this->config,
            'stats' => $this->stats,
            'servers' => array_keys($this->servers),
            'resources' => array_keys($this->resources),
            'tools' => array_keys($this->tools),
        ];
    }

    /**
     * Convert the manager to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'config' => $this->config,
            'stats' => $this->stats,
            'servers' => array_map(fn($s) => $s->toArray(), $this->servers),
            'resources' => array_map(fn($r) => $r->toArray(), $this->resources),
            'tools' => array_map(fn($t) => $t->toArray(), $this->tools),
        ];
    }

    /**
     * Create an MCPManager from an array.
     *
     * @param array $data The manager data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $manager = new self($data['config'] ?? []);

        if (isset($data['enabled'])) {
            $manager->setEnabled($data['enabled']);
        }

        if (isset($data['servers'])) {
            foreach ($data['servers'] as $serverData) {
                $server = MCPServer::fromArray($serverData);
                $manager->servers[$server->getName()] = $server;
            }
        }

        if (isset($data['resources'])) {
            foreach ($data['resources'] as $resourceData) {
                $resource = MCPResource::fromArray($resourceData);
                $manager->resources[$resource->getName()] = $resource;
            }
        }

        if (isset($data['tools'])) {
            foreach ($data['tools'] as $toolData) {
                // Note: Tools need to be recreated since they contain closures
                // This is a simplified version
                $manager->stats['total_tools']++;
            }
        }

        $manager->updateStats();
        return $manager;
    }

    /**
     * Stream a resource from a specific server.
     *
     * @param string $serverName The server name
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @return iterable
     */
    public function streamResource(string $serverName, string $resourceName, array $parameters = []): iterable
    {
        $server = $this->getServer($serverName);
        if (!$server) {
            throw new Exception("Server '{$serverName}' not found");
        }

        return $server->streamResource($resourceName, $parameters);
    }

    /**
     * Subscribe to events from a specific server.
     *
     * @param string $serverName The server name
     * @param string $eventType The event type
     * @param array $filters Optional filters
     * @return iterable
     */
    public function subscribeToEvents(string $serverName, string $eventType, array $filters = []): iterable
    {
        $server = $this->getServer($serverName);
        if (!$server) {
            throw new Exception("Server '{$serverName}' not found");
        }

        return $server->subscribeToEvents($eventType, $filters);
    }

    /**
     * Stream a resource with callback for real-time processing.
     *
     * @param string $serverName The server name
     * @param string $resourceName The resource name
     * @param array|null $parameters The resource parameters
     * @param callable|null $callback Callback function for each chunk
     * @return void
     * @throws Exception
     */
    public function streamResourceWithCallback(string $serverName, string $resourceName, array|null $parameters = null, callable|null $callback = null): void
    {
        $parameters ??= [];
        $server = $this->getServer($serverName);
        if (!$server) {
            throw new Exception("Server '{$serverName}' not found");
        }

        foreach ($server->streamResource($resourceName, $parameters) as $chunk) {
            if ($callback) {
                $callback($chunk);
            }
        }
    }


    /**
     * Get all servers that support SSE.
     *
     * @return array
     */
    public function getServersWithSSE(): array
    {
        $sseServers = [];

        foreach ($this->servers as $serverName => $server) {
            if ($server->isEnabled() && $server->supportsSSE()) {
                $sseServers[$serverName] = $server;
            }
        }

        return $sseServers;
    }

    /**
     * Get statistics about MCP usage including SSE support.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_servers' => count($this->servers),
            'enabled_servers' => count($this->getEnabledServers()),
            'total_resources' => count($this->resources),
            'total_tools' => count($this->tools),
            'servers_with_sse' => count($this->getServersWithSSE()),
            'servers' => []
        ];

        foreach ($this->servers as $serverName => $server) {
            $serverStats = [
                'name' => $serverName,
                'enabled' => $server->isEnabled(),
                'url' => $server->getUrl(),
                'resources' => count($server->getResources()),
                'tools' => 0, // Tools are managed globally
                'supports_sse' => $server->supportsSSE()
            ];
            $stats['servers'][] = $serverStats;
        }

        return $stats;
    }
}
