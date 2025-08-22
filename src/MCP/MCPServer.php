<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

use Illuminate\Support\Facades\Log;

/**
 * Class MCPServer
 *
 * Manages MCP server connections and resources.
 */
class MCPServer
{
    /**
     * The server name
     */
    private string $name;

    /**
     * The server URL
     */
    private string $url;

    /**
     * The server resources
     */
    private array $resources = [];

    /**
     * The MCP client (HTTP)
     */
    private ?MCPClient $httpClient = null;

    /**
     * The MCP STDIO client
     */
    private ?MCPSTDIOClient $stdioClient = null;

    /**
     * The transport type
     */
    private string $transport = 'http';

    /**
     * Whether the server is enabled
     */
    private bool $enabled = true;

    /**
     * Server metadata
     */
    private array $metadata = [];

    /**
     * Server capabilities
     */
    private array $capabilities = [];

    /**
     * Create a new MCPServer instance.
     *
     * @param string $name The server name
     * @param string $url The server URL or command
     * @param array $config Server configuration
     */
    public function __construct(string $name, string $url, array $config = [])
    {
        $this->name = $name;
        $this->url = $url;
        
        $transport = $config['transport'] ?? 'http';
        $this->transport = $transport;
        
        if ($transport === 'stdio') {
            // STDIO transport
            $command = $config['command'] ?? $url;
            $arguments = $config['arguments'] ?? [];
            $workingDirectory = $config['working_directory'] ?? '';
            $environment = $config['environment'] ?? [];
            $timeout = $config['timeout'] ?? 30;
            $enableLogging = $config['enable_logging'] ?? true;
            
            $this->stdioClient = new MCPSTDIOClient(
                $command,
                $arguments,
                $workingDirectory,
                $environment,
                $timeout,
                $enableLogging
            );
        } else {
            // HTTP transport
            $headers = $config['headers'] ?? [];
            $timeout = $config['timeout'] ?? 30;
            $maxRetries = $config['max_retries'] ?? 3;
            $enableLogging = $config['enable_logging'] ?? true;
            $format = $config['format'] ?? 'auto';

            $clientUrl = $url;
            if (isset($config['sse_url']) && !empty($config['sse_url'])) {
                $clientUrl = $config['sse_url'];
            }
            
            $this->httpClient = new MCPClient($clientUrl, $headers, $timeout, $maxRetries, $enableLogging, $format);
        }
        
        if (isset($config['enabled'])) {
            $this->enabled = $config['enabled'];
        }
        
        if (isset($config['metadata'])) {
            $this->metadata = $config['metadata'];
        }
        
        if (isset($config['capabilities'])) {
            $this->capabilities = $config['capabilities'];
        }
    }

    /**
     * Get the server name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the server URL.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the MCP client.
     *
     * @return MCPClient|MCPSTDIOClient
     */
    public function getClient()
    {
        return $this->transport === 'stdio' ? $this->stdioClient : $this->httpClient;
    }

    /**
     * Get the transport type.
     *
     * @return string
     */
    public function getTransport(): string
    {
        return $this->transport;
    }

    /**
     * Check if the server uses STDIO transport.
     *
     * @return bool
     */
    public function isSTDIO(): bool
    {
        return $this->transport === 'stdio';
    }

    /**
     * Check if the server uses HTTP transport.
     *
     * @return bool
     */
    public function isHTTP(): bool
    {
        return $this->transport === 'http';
    }

    /**
     * Get server resources.
     *
     * @return array
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Add a resource to the server.
     *
     * @param MCPResource $resource The resource to add
     * @return self
     */
    public function addResource(MCPResource $resource): self
    {
        $this->resources[$resource->getName()] = $resource;
        return $this;
    }

    /**
     * Get a specific resource.
     *
     * @param string $resourceName The resource name
     * @return MCPResource|null
     */
    public function getResource(string $resourceName): ?MCPResource
    {
        return $this->resources[$resourceName] ?? null;
    }

    /**
     * Remove a resource from the server.
     *
     * @param string $resourceName The resource name
     * @return self
     */
    public function removeResource(string $resourceName): self
    {
        unset($this->resources[$resourceName]);
        return $this;
    }

    /**
     * Check if the server has a specific resource.
     *
     * @param string $resourceName The resource name
     * @return bool
     */
    public function hasResource(string $resourceName): bool
    {
        return isset($this->resources[$resourceName]);
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
     * Check if the server is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set whether the server is enabled.
     *
     * @param bool $enabled Whether the server is enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get server metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set server metadata.
     *
     * @param array $metadata The server metadata
     * @return self
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Add metadata to the server.
     *
     * @param string $key The metadata key
     * @param mixed $value The metadata value
     * @return self
     */
    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get server capabilities.
     *
     * @return array
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Set server capabilities.
     *
     * @param array $capabilities The server capabilities
     * @return self
     */
    public function setCapabilities(array $capabilities): self
    {
        $this->capabilities = $capabilities;
        return $this;
    }

    /**
     * Test connection to the MCP server.
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $client = $this->getClient();
        if (!$client) {
            return false;
        }

        return $client->testConnection();
    }

    /**
     * Discover server resources.
     *
     * @return array
     */
    public function discoverResources(): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $client = $this->getClient();
            if (!$client) {
                return [];
            }

            if ($this->isSTDIO()) {
                $discoveredResources = $client->listResources();
            } else {
                $discoveredResources = $client->discoverResources();
            }
            
            // Convert discovered resources to MCPResource objects
            foreach ($discoveredResources as $resourceData) {
                if (isset($resourceData['name'])) {
                    $resource = MCPResource::fromArray($resourceData);
                    $this->addResource($resource);
                }
            }

            Log::info('MCP server resource discovery completed', [
                'server' => $this->name,
                'transport' => $this->transport,
                'resources_found' => count($discoveredResources)
            ]);

            return $discoveredResources;
        } catch (\Exception $e) {
            Log::error('MCP server resource discovery failed', [
                'server' => $this->name,
                'transport' => $this->transport,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Call a resource on the server.
     *
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @return array
     */
    public function callResource(string $resourceName, array $parameters = []): array
    {
        if (!$this->enabled) {
            return [
                'error' => 'Server is disabled',
                'status' => 'error'
            ];
        }

        $resource = $this->getResource($resourceName);
        if (!$resource) {
            return [
                'error' => "Resource '{$resourceName}' not found",
                'status' => 'error'
            ];
        }

        if (!$resource->isEnabled()) {
            return [
                'error' => "Resource '{$resourceName}' is disabled",
                'status' => 'error'
            ];
        }

        // Validate parameters
        $validationErrors = $resource->validateParameters($parameters);
        if (!empty($validationErrors)) {
            return [
                'error' => 'Parameter validation failed: ' . implode(', ', $validationErrors),
                'status' => 'error'
            ];
        }

        // Call the resource
        $client = $this->getClient();
        if (!$client) {
            return [
                'error' => 'Client not available',
                'status' => 'error'
            ];
        }

        return $client->callResource($resourceName, $parameters);
    }

    /**
     * Stream a resource with SSE.
     *
     * @param string $resourceName The resource name
     * @param array $parameters The resource parameters
     * @return iterable
     */
    public function streamResource(string $resourceName, array $parameters = []): iterable
    {
        if (!$this->enabled) {
            throw new \Exception('Server is disabled');
        }

        $resource = $this->getResource($resourceName);
        if (!$resource) {
            throw new \Exception("Resource '{$resourceName}' not found");
        }

        if (!$resource->isEnabled()) {
            throw new \Exception("Resource '{$resourceName}' is disabled");
        }

        // Validate parameters
        $validationErrors = $resource->validateParameters($parameters);
        if (!empty($validationErrors)) {
            throw new \Exception('Parameter validation failed: ' . implode(', ', $validationErrors));
        }

        // Check if server supports SSE (only for HTTP transport)
        if ($this->isHTTP()) {
            $client = $this->getClient();
            if (!$client->supportsSSE()) {
                throw new \Exception('Server does not support SSE streaming');
            }
        } else {
            // STDIO doesn't support streaming in the same way
            throw new \Exception('STDIO transport does not support streaming');
        }

        // Stream the resource
        return $client->streamResource($resourceName, $parameters);
    }

    /**
     * Subscribe to server events.
     *
     * @param string $eventType The event type
     * @param array $filters Optional filters
     * @return iterable
     */
    public function subscribeToEvents(string $eventType, array $filters = []): iterable
    {
        if (!$this->enabled) {
            throw new \Exception('Server is disabled');
        }

        // Check if server supports SSE (only for HTTP transport)
        if ($this->isHTTP()) {
            $client = $this->getClient();
            if (!$client->supportsSSE()) {
                throw new \Exception('Server does not support SSE streaming');
            }
        } else {
            // STDIO doesn't support event subscription
            throw new \Exception('STDIO transport does not support event subscription');
        }

        return $client->subscribeToEvents($eventType, $filters);
    }

    /**
     * Check if the server supports SSE.
     *
     * @return bool
     */
    public function supportsSSE(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Only HTTP transport supports SSE
        if (!$this->isHTTP()) {
            return false;
        }

        $client = $this->getClient();
        return $client ? $client->supportsSSE() : false;
    }

    /**
     * Get server information.
     *
     * @return array
     */
    public function getServerInfo(): array
    {
        if (!$this->enabled) {
            return [
                'name' => $this->name,
                'url' => $this->url,
                'transport' => $this->transport,
                'enabled' => false,
                'error' => 'Server is disabled'
            ];
        }

        $client = $this->getClient();
        if (!$client) {
            return [
                'name' => $this->name,
                'url' => $this->url,
                'transport' => $this->transport,
                'enabled' => $this->enabled,
                'error' => 'Client not available'
            ];
        }

        $serverInfo = $client->getServerInfo();
        $serverInfo['name'] = $this->name;
        $serverInfo['url'] = $this->url;
        $serverInfo['transport'] = $this->transport;
        $serverInfo['enabled'] = $this->enabled;
        $serverInfo['resources_count'] = count($this->resources);
        $serverInfo['capabilities'] = $this->capabilities;
        $serverInfo['metadata'] = $this->metadata;

        return $serverInfo;
    }

    /**
     * Get server statistics.
     *
     * @return array
     */
    public function getServerStats(): array
    {
        if (!$this->enabled) {
            return [
                'name' => $this->name,
                'transport' => $this->transport,
                'enabled' => false,
                'resources_count' => 0,
                'tools_count' => 0
            ];
        }

        $client = $this->getClient();
        if (!$client) {
            return [
                'name' => $this->name,
                'transport' => $this->transport,
                'enabled' => $this->enabled,
                'resources_count' => count($this->resources),
                'tools_count' => 0,
                'error' => 'Client not available'
            ];
        }

        $stats = [];
        
        if ($this->isHTTP()) {
            $stats = $client->getServerStats();
        } else {
            // For STDIO, we can get process information
            $stats = [
                'process_id' => $client->getProcessId(),
                'is_running' => $client->isRunning(),
                'process_status' => $client->getProcessStatus()
            ];
        }

        $stats['name'] = $this->name;
        $stats['transport'] = $this->transport;
        $stats['enabled'] = $this->enabled;
        $stats['resources_count'] = count($this->resources);
        $stats['capabilities'] = $this->capabilities;

        return $stats;
    }

    /**
     * Validate server capabilities.
     *
     * @param array $requiredCapabilities The required capabilities
     * @return array Array of missing capabilities
     */
    public function validateCapabilities(array $requiredCapabilities): array
    {
        if (!$this->enabled) {
            return $requiredCapabilities;
        }

        return $this->client->validateCapabilities($requiredCapabilities);
    }

    /**
     * Check if the server supports a specific capability.
     *
     * @param string $capability The capability to check
     * @return bool
     */
    public function supportsCapability(string $capability): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->client->supportsCapability($capability);
    }

    /**
     * Get resource names.
     *
     * @return array
     */
    public function getResourceNames(): array
    {
        return array_keys($this->resources);
    }

    /**
     * Get enabled resource names.
     *
     * @return array
     */
    public function getEnabledResourceNames(): array
    {
        return array_keys($this->getEnabledResources());
    }

    /**
     * Convert the server to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $client = $this->getClient();
        $clientData = $client ? $client->toArray() : [];
        
        return [
            'name' => $this->name,
            'url' => $this->url,
            'transport' => $this->transport,
            'enabled' => $this->enabled,
            'resources' => array_map(fn($r) => $r->toArray(), $this->resources),
            'capabilities' => $this->capabilities,
            'metadata' => $this->metadata,
            'client' => $clientData,
        ];
    }

    /**
     * Create an MCPServer from an array.
     *
     * @param array $data The server data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $config = $data['config'] ?? [];
        if (isset($data['transport'])) {
            $config['transport'] = $data['transport'];
        }
        
        $server = new self($data['name'], $data['url'], $config);

        if (isset($data['enabled'])) {
            $server->setEnabled($data['enabled']);
        }

        if (isset($data['capabilities'])) {
            $server->setCapabilities($data['capabilities']);
        }

        if (isset($data['metadata'])) {
            $server->setMetadata($data['metadata']);
        }

        if (isset($data['resources'])) {
            foreach ($data['resources'] as $resourceData) {
                $resource = MCPResource::fromArray($resourceData);
                $server->addResource($resource);
            }
        }

        return $server;
    }
} 