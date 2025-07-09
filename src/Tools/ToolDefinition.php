<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tools;

/**
 * Class ToolDefinition
 *
 * Represents a complete tool definition with strong typing support.
 * This class provides a fluent interface for building tool definitions
 * with name, description, schema, and callback function.
 */
class ToolDefinition
{
    /**
     * The tool name
     */
    private string $name;

    /**
     * The tool description
     */
    private ?string $description = null;

    /**
     * The tool schema
     */
    private ToolSchema $schema;

    /**
     * The tool callback function
     */
    private \Closure $callback;

    /**
     * Create a new ToolDefinition instance.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param ToolSchema $schema The tool schema
     */
    public function __construct(string $name, \Closure $callback, ToolSchema $schema)
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->schema = $schema;
    }

    /**
     * Set the tool description.
     *
     * @param string $description The description
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the tool name.
     *
     * @return string The tool name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the tool description.
     *
     * @return string|null The tool description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the tool schema.
     *
     * @return ToolSchema The tool schema
     */
    public function getSchema(): ToolSchema
    {
        return $this->schema;
    }

    /**
     * Get the tool callback function.
     *
     * @return \Closure The tool callback function
     */
    public function getCallback(): \Closure
    {
        return $this->callback;
    }

    /**
     * Convert the tool definition to an array representation.
     *
     * @return array The tool definition as an array
     */
    public function toArray(): array
    {
        $definition = [
            'name' => $this->name,
            'schema' => $this->schema->toArray(),
        ];

        if ($this->description !== null) {
            $definition['description'] = $this->description;
        }

        return $definition;
    }

    /**
     * Create a simple tool definition with a string parameter.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return self
     */
    public static function withStringParam(string $name, \Closure $callback, string $paramName, string $paramDescription = ''): self
    {
        $schema = ToolSchema::stringParam($paramName, $paramDescription);
        return new self($name, $callback, $schema);
    }

    /**
     * Create a simple tool definition with an integer parameter.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return self
     */
    public static function withIntegerParam(string $name, \Closure $callback, string $paramName, string $paramDescription = ''): self
    {
        $schema = ToolSchema::integerParam($paramName, $paramDescription);
        return new self($name, $callback, $schema);
    }

    /**
     * Create a simple tool definition with a number parameter.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return self
     */
    public static function withNumberParam(string $name, \Closure $callback, string $paramName, string $paramDescription = ''): self
    {
        $schema = ToolSchema::numberParam($paramName, $paramDescription);
        return new self($name, $callback, $schema);
    }

    /**
     * Create a simple tool definition with a boolean parameter.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param string $paramName The parameter name
     * @param string $paramDescription The parameter description
     * @return self
     */
    public static function withBooleanParam(string $name, \Closure $callback, string $paramName, string $paramDescription = ''): self
    {
        $schema = ToolSchema::booleanParam($paramName, $paramDescription);
        return new self($name, $callback, $schema);
    }

    /**
     * Create a tool definition with no parameters.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @return self
     */
    public static function withNoParams(string $name, \Closure $callback): self
    {
        $schema = ToolSchema::empty();
        return new self($name, $callback, $schema);
    }

    /**
     * Create a tool definition with a custom schema.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @param ToolSchema $schema The tool schema
     * @return self
     */
    public static function withSchema(string $name, \Closure $callback, ToolSchema $schema): self
    {
        return new self($name, $callback, $schema);
    }

    /**
     * Create a tool definition builder.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     * @return ToolDefinitionBuilder
     */
    public static function builder(string $name, \Closure $callback): ToolDefinitionBuilder
    {
        return new ToolDefinitionBuilder($name, $callback);
    }
}

/**
 * Class ToolDefinitionBuilder
 *
 * Builder class for creating complex tool definitions with fluent interface.
 */
class ToolDefinitionBuilder
{
    /**
     * The tool name
     */
    private string $name;

    /**
     * The tool callback function
     */
    private \Closure $callback;

    /**
     * The tool schema
     */
    private ToolSchema $schema;

    /**
     * The tool description
     */
    private ?string $description = null;

    /**
     * Create a new ToolDefinitionBuilder instance.
     *
     * @param string $name The tool name
     * @param \Closure $callback The tool callback function
     */
    public function __construct(string $name, \Closure $callback)
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->schema = ToolSchema::create();
    }

    /**
     * Set the tool description.
     *
     * @param string $description The description
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Add a string property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function stringProperty(string $name, string $description = ''): self
    {
        $this->schema->stringProperty($name, $description);
        return $this;
    }

    /**
     * Add an integer property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function integerProperty(string $name, string $description = ''): self
    {
        $this->schema->integerProperty($name, $description);
        return $this;
    }

    /**
     * Add a number property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function numberProperty(string $name, string $description = ''): self
    {
        $this->schema->numberProperty($name, $description);
        return $this;
    }

    /**
     * Add a boolean property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function booleanProperty(string $name, string $description = ''): self
    {
        $this->schema->booleanProperty($name, $description);
        return $this;
    }

    /**
     * Add an array property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function arrayProperty(string $name, string $description = ''): self
    {
        $this->schema->arrayProperty($name, $description);
        return $this;
    }

    /**
     * Add an object property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function objectProperty(string $name, string $description = ''): self
    {
        $this->schema->objectProperty($name, $description);
        return $this;
    }

    /**
     * Add a required string property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredStringProperty(string $name, string $description = ''): self
    {
        $this->schema->requiredStringProperty($name, $description);
        return $this;
    }

    /**
     * Add a required integer property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredIntegerProperty(string $name, string $description = ''): self
    {
        $this->schema->requiredIntegerProperty($name, $description);
        return $this;
    }

    /**
     * Add a required number property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredNumberProperty(string $name, string $description = ''): self
    {
        $this->schema->requiredNumberProperty($name, $description);
        return $this;
    }

    /**
     * Add a required boolean property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredBooleanProperty(string $name, string $description = ''): self
    {
        $this->schema->requiredBooleanProperty($name, $description);
        return $this;
    }

    /**
     * Add a required array property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredArrayProperty(string $name, string $description = ''): self
    {
        $this->schema->requiredArrayProperty($name, $description);
        return $this;
    }

    /**
     * Add a required object property to the schema.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredObjectProperty(string $name, string $description = ''): self
    {
        $this->schema->requiredObjectProperty($name, $description);
        return $this;
    }

    /**
     * Add a property with validation to the schema.
     *
     * @param string $name The property name
     * @param ToolProperty $property The property definition
     * @return self
     */
    public function property(string $name, ToolProperty $property): self
    {
        $this->schema->property($name, $property);
        return $this;
    }

    /**
     * Build the tool definition.
     *
     * @return ToolDefinition The tool definition
     */
    public function build(): ToolDefinition
    {
        $definition = new ToolDefinition($this->name, $this->callback, $this->schema);
        
        if ($this->description !== null) {
            $definition->description($this->description);
        }
        
        return $definition;
    }
} 