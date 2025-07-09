<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\MCP;

/**
 * Class MCPResource
 *
 * Represents an MCP (Model Context Protocol) resource that can be accessed
 * by AI agents through the MCP protocol.
 */
class MCPResource
{
    /**
     * The resource name
     */
    private string $name;

    /**
     * The resource description
     */
    private string $description;

    /**
     * The resource URI
     */
    private string $uri;

    /**
     * The resource parameters schema
     */
    private array $parameters;

    /**
     * The resource schema
     */
    private array $schema;

    /**
     * Required permissions for this resource
     */
    private array $requiredPermissions = [];

    /**
     * Whether the resource is enabled
     */
    private bool $enabled = true;

    /**
     * Resource metadata
     */
    private array $metadata = [];

    /**
     * Create a new MCPResource instance.
     *
     * @param string $name The resource name
     * @param string $description The resource description
     * @param string $uri The resource URI
     * @param array $parameters The resource parameters
     * @param array $schema The resource schema
     */
    public function __construct(
        string $name,
        string $description,
        string $uri,
        array $parameters = [],
        array $schema = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->uri = $uri;
        $this->parameters = $parameters;
        $this->schema = $schema;
    }

    /**
     * Get the resource name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the resource description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the resource URI.
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the resource parameters.
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get the resource schema.
     *
     * @return array
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Set the resource schema.
     *
     * @param array $schema The resource schema
     * @return self
     */
    public function setSchema(array $schema): self
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * Get required permissions.
     *
     * @return array
     */
    public function getRequiredPermissions(): array
    {
        return $this->requiredPermissions;
    }

    /**
     * Set required permissions.
     *
     * @param array $permissions The required permissions
     * @return self
     */
    public function setRequiredPermissions(array $permissions): self
    {
        $this->requiredPermissions = $permissions;
        return $this;
    }

    /**
     * Check if the resource is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set whether the resource is enabled.
     *
     * @param bool $enabled Whether the resource is enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get resource metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set resource metadata.
     *
     * @param array $metadata The resource metadata
     * @return self
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Add metadata to the resource.
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
     * Set parameter schema.
     *
     * @param array $parameterSchema The parameter schema
     * @return self
     */
    public function setParameterSchema(array $parameterSchema): self
    {
        $this->parameters = $parameterSchema;
        return $this;
    }

    /**
     * Validate parameters against the schema.
     *
     * @param array $params The parameters to validate
     * @return array Array of validation errors
     */
    public function validateParameters(array $params): array
    {
        $errors = [];

        foreach ($this->parameters as $paramName => $paramSchema) {
            if (isset($paramSchema['required']) && $paramSchema['required'] && !isset($params[$paramName])) {
                $errors[] = "Required parameter '{$paramName}' is missing";
                continue;
            }

            if (isset($params[$paramName])) {
                $value = $params[$paramName];
                $type = $paramSchema['type'] ?? 'string';

                // Type validation
                switch ($type) {
                    case 'string':
                        if (!is_string($value)) {
                            $errors[] = "Parameter '{$paramName}' must be a string";
                        }
                        break;
                    case 'integer':
                        if (!is_int($value)) {
                            $errors[] = "Parameter '{$paramName}' must be an integer";
                        }
                        break;
                    case 'number':
                        if (!is_numeric($value)) {
                            $errors[] = "Parameter '{$paramName}' must be a number";
                        }
                        break;
                    case 'boolean':
                        if (!is_bool($value)) {
                            $errors[] = "Parameter '{$paramName}' must be a boolean";
                        }
                        break;
                    case 'array':
                        if (!is_array($value)) {
                            $errors[] = "Parameter '{$paramName}' must be an array";
                        }
                        break;
                }

                // Length validation for strings
                if ($type === 'string' && isset($paramSchema['minLength'])) {
                    if (strlen($value) < $paramSchema['minLength']) {
                        $errors[] = "Parameter '{$paramName}' must be at least {$paramSchema['minLength']} characters";
                    }
                }

                if ($type === 'string' && isset($paramSchema['maxLength'])) {
                    if (strlen($value) > $paramSchema['maxLength']) {
                        $errors[] = "Parameter '{$paramName}' must be at most {$paramSchema['maxLength']} characters";
                    }
                }

                // Range validation for numbers
                if (in_array($type, ['integer', 'number']) && isset($paramSchema['minimum'])) {
                    if ($value < $paramSchema['minimum']) {
                        $errors[] = "Parameter '{$paramName}' must be at least {$paramSchema['minimum']}";
                    }
                }

                if (in_array($type, ['integer', 'number']) && isset($paramSchema['maximum'])) {
                    if ($value > $paramSchema['maximum']) {
                        $errors[] = "Parameter '{$paramName}' must be at most {$paramSchema['maximum']}";
                    }
                }

                // Enum validation
                if (isset($paramSchema['enum']) && !in_array($value, $paramSchema['enum'])) {
                    $allowed = implode(', ', $paramSchema['enum']);
                    $errors[] = "Parameter '{$paramName}' must be one of: {$allowed}";
                }
            }
        }

        return $errors;
    }

    /**
     * Convert the resource to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'uri' => $this->uri,
            'parameters' => $this->parameters,
            'schema' => $this->schema,
            'required_permissions' => $this->requiredPermissions,
            'enabled' => $this->enabled,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create an MCPResource from an array.
     *
     * @param array $data The resource data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $resource = new self(
            $data['name'],
            $data['description'],
            $data['uri'],
            $data['parameters'] ?? [],
            $data['schema'] ?? []
        );

        if (isset($data['required_permissions'])) {
            $resource->setRequiredPermissions($data['required_permissions']);
        }

        if (isset($data['enabled'])) {
            $resource->setEnabled($data['enabled']);
        }

        if (isset($data['metadata'])) {
            $resource->setMetadata($data['metadata']);
        }

        return $resource;
    }

    /**
     * Create a simple string parameter resource.
     *
     * @param string $name The resource name
     * @param string $description The resource description
     * @param string $uri The resource URI
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return self
     */
    public static function withStringParam(
        string $name,
        string $description,
        string $uri,
        string $paramName,
        string $paramDescription = ''
    ): self {
        return new self($name, $description, $uri, [
            $paramName => [
                'type' => 'string',
                'description' => $paramDescription,
                'required' => true
            ]
        ]);
    }

    /**
     * Create a simple integer parameter resource.
     *
     * @param string $name The resource name
     * @param string $description The resource description
     * @param string $uri The resource URI
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return self
     */
    public static function withIntegerParam(
        string $name,
        string $description,
        string $uri,
        string $paramName,
        string $paramDescription = ''
    ): self {
        return new self($name, $description, $uri, [
            $paramName => [
                'type' => 'integer',
                'description' => $paramDescription,
                'required' => true
            ]
        ]);
    }

    /**
     * Create a no-parameter resource.
     *
     * @param string $name The resource name
     * @param string $description The resource description
     * @param string $uri The resource URI
     * @return self
     */
    public static function withNoParams(
        string $name,
        string $description,
        string $uri
    ): self {
        return new self($name, $description, $uri);
    }
} 