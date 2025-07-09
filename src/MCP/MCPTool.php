<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

/**
 * Class MCPTool
 *
 * Wraps MCP resources as tools for agents to use.
 */
class MCPTool
{
    /**
     * The MCP resource
     */
    private MCPResource $resource;

    /**
     * The MCP server
     */
    private MCPServer $server;

    /**
     * The tool callback function
     */
    private \Closure $callback;

    /**
     * The tool name (may differ from resource name)
     */
    private string $toolName;

    /**
     * Tool metadata
     */
    private array $metadata = [];

    /**
     * Create a new MCPTool instance.
     *
     * @param string $toolName The tool name
     * @param MCPResource $resource The MCP resource
     * @param MCPServer $server The MCP server
     * @param \Closure $callback The tool callback function
     */
    public function __construct(
        string $toolName,
        MCPResource $resource,
        MCPServer $server,
        \Closure $callback
    ) {
        $this->toolName = $toolName;
        $this->resource = $resource;
        $this->server = $server;
        $this->callback = $callback;
    }

    /**
     * Get the tool name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->toolName;
    }

    /**
     * Get the MCP resource.
     *
     * @return MCPResource
     */
    public function getResource(): MCPResource
    {
        return $this->resource;
    }

    /**
     * Get the MCP server.
     *
     * @return MCPServer
     */
    public function getServer(): MCPServer
    {
        return $this->server;
    }

    /**
     * Get the tool callback function.
     *
     * @return \Closure
     */
    public function getCallback(): \Closure
    {
        return $this->callback;
    }

    /**
     * Get tool metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set tool metadata.
     *
     * @param array $metadata The tool metadata
     * @return self
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Add metadata to the tool.
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
     * Execute the tool with the given parameters.
     *
     * @param array $parameters The tool parameters
     * @return mixed
     */
    public function execute(array $parameters = [])
    {
        // Check if server is enabled
        if (!$this->server->isEnabled()) {
            throw new \Exception("MCP server '{$this->server->getName()}' is disabled");
        }

        // Check if resource is enabled
        if (!$this->resource->isEnabled()) {
            throw new \Exception("MCP resource '{$this->resource->getName()}' is disabled");
        }

        // Validate parameters
        $validationErrors = $this->resource->validateParameters($parameters);
        if (!empty($validationErrors)) {
            throw new \Exception('Parameter validation failed: ' . implode(', ', $validationErrors));
        }

        // Directly execute the callback with the parameters
        return ($this->callback)($parameters);
    }

    /**
     * Get the tool schema for agent registration.
     *
     * @return array
     */
    public function getSchema(): array
    {
        $resourceSchema = $this->resource->getSchema();
        $parameters = $this->resource->getParameters();

        // Convert parameters to schema properties
        $properties = [];
        $required = [];

        foreach ($parameters as $paramName => $paramConfig) {
            $properties[$paramName] = [
                'type' => $paramConfig['type'] ?? 'string',
                'description' => $paramConfig['description'] ?? '',
            ];

            // Add additional validation rules
            if (isset($paramConfig['minimum'])) {
                $properties[$paramName]['minimum'] = $paramConfig['minimum'];
            }

            if (isset($paramConfig['maximum'])) {
                $properties[$paramName]['maximum'] = $paramConfig['maximum'];
            }

            if (isset($paramConfig['minLength'])) {
                $properties[$paramName]['minLength'] = $paramConfig['minLength'];
            }

            if (isset($paramConfig['maxLength'])) {
                $properties[$paramName]['maxLength'] = $paramConfig['maxLength'];
            }

            if (isset($paramConfig['enum'])) {
                $properties[$paramName]['enum'] = $paramConfig['enum'];
            }

            if (isset($paramConfig['pattern'])) {
                $properties[$paramName]['pattern'] = $paramConfig['pattern'];
            }

            if (isset($paramConfig['default'])) {
                $properties[$paramName]['default'] = $paramConfig['default'];
            }

            // Add to required if specified
            if (isset($paramConfig['required']) && $paramConfig['required']) {
                $required[] = $paramName;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Get the tool definition for agent registration.
     *
     * @return array
     */
    public function getToolDefinition(): array
    {
        return [
            'name' => $this->toolName,
            'description' => $this->resource->getDescription(),
            'schema' => $this->getSchema(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if the tool is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->server->isEnabled() && $this->resource->isEnabled();
    }

    /**
     * Get tool information.
     *
     * @return array
     */
    public function getInfo(): array
    {
        return [
            'name' => $this->toolName,
            'description' => $this->resource->getDescription(),
            'server' => $this->server->getName(),
            'resource' => $this->resource->getName(),
            'uri' => $this->resource->getUri(),
            'enabled' => $this->isEnabled(),
            'parameters' => $this->resource->getParameters(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert the tool to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->toolName,
            'resource' => $this->resource->toArray(),
            'server' => $this->server->getName(),
            'enabled' => $this->isEnabled(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create an MCPTool from a resource and server.
     *
     * @param string $toolName The tool name
     * @param MCPResource $resource The MCP resource
     * @param MCPServer $server The MCP server
     * @param \Closure $callback The tool callback function
     * @return self
     */
    public static function fromResource(
        string $toolName,
        MCPResource $resource,
        MCPServer $server,
        \Closure $callback
    ): self {
        return new self($toolName, $resource, $server, $callback);
    }

    /**
     * Create a simple MCPTool with default callback.
     *
     * @param string $toolName The tool name
     * @param MCPResource $resource The MCP resource
     * @param MCPServer $server The MCP server
     * @return self
     */
    public static function create(
        string $toolName,
        MCPResource $resource,
        MCPServer $server
    ): self {
        $callback = function ($result, $parameters) {
            // Default callback: return the result as is
            return $result;
        };

        return new self($toolName, $resource, $server, $callback);
    }

    /**
     * Create an MCPTool with custom result processing.
     *
     * @param string $toolName The tool name
     * @param MCPResource $resource The MCP resource
     * @param MCPServer $server The MCP server
     * @param callable $processor The result processor function
     * @return self
     */
    public static function withProcessor(
        string $toolName,
        MCPResource $resource,
        MCPServer $server,
        callable $processor
    ): self {
        $callback = function ($result, $parameters) use ($processor) {
            return $processor($result, $parameters);
        };

        return new self($toolName, $resource, $server, $callback);
    }
} 