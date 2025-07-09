<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tools;

/**
 * Class ToolSchema
 *
 * Represents a complete tool schema with strong typing support.
 * This class provides a fluent interface for building tool schemas
 * with properties, validation rules, and constraints.
 */
class ToolSchema
{
    /**
     * The schema type (always 'object' for tools)
     */
    private string $type = 'object';

    /**
     * The schema description
     */
    private ?string $description = null;

    /**
     * The schema properties
     */
    private array $properties = [];

    /**
     * Required properties
     */
    private array $required = [];

    /**
     * Additional schema properties
     */
    private array $additionalProperties = [];

    /**
     * Create a new ToolSchema instance.
     */
    public function __construct()
    {
    }

    /**
     * Set the schema description.
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
     * Add a property to the schema.
     *
     * @param string $name The property name
     * @param ToolProperty $property The property definition
     * @return self
     */
    public function property(string $name, ToolProperty $property): self
    {
        $this->properties[$name] = $property->toArray();
        
        if ($property->isRequired()) {
            $this->required[] = $name;
        }
        
        return $this;
    }

    /**
     * Add a string property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function stringProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::string($description));
    }

    /**
     * Add an integer property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function integerProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::integer($description));
    }

    /**
     * Add a number property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function numberProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::number($description));
    }

    /**
     * Add a boolean property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function booleanProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::boolean($description));
    }

    /**
     * Add an array property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function arrayProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::array($description));
    }

    /**
     * Add an object property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function objectProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::object($description));
    }

    /**
     * Add a required string property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredStringProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::string($description)->required());
    }

    /**
     * Add a required integer property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredIntegerProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::integer($description)->required());
    }

    /**
     * Add a required number property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredNumberProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::number($description)->required());
    }

    /**
     * Add a required boolean property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredBooleanProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::boolean($description)->required());
    }

    /**
     * Add a required array property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredArrayProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::array($description)->required());
    }

    /**
     * Add a required object property.
     *
     * @param string $name The property name
     * @param string $description The property description
     * @return self
     */
    public function requiredObjectProperty(string $name, string $description = ''): self
    {
        return $this->property($name, ToolProperty::object($description)->required());
    }

    /**
     * Add additional schema properties.
     *
     * @param array $properties Additional properties
     * @return self
     */
    public function additionalProperties(array $properties): self
    {
        $this->additionalProperties = array_merge($this->additionalProperties, $properties);
        return $this;
    }

    /**
     * Convert the schema to an array representation.
     *
     * @return array The schema as an array
     */
    public function toArray(): array
    {
        $schema = [
            'type' => $this->type,
            'properties' => $this->properties,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if (!empty($this->required)) {
            $schema['required'] = $this->required;
        }

        if (!empty($this->additionalProperties)) {
            $schema = array_merge($schema, $this->additionalProperties);
        }

        return $schema;
    }

    /**
     * Create a new schema instance.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create a simple schema with no properties.
     *
     * @return self
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Create a schema for a simple string parameter.
     *
     * @param string $paramName The parameter name
     * @param string $description The parameter description
     * @return self
     */
    public static function stringParam(string $paramName, string $description = ''): self
    {
        return (new self())
            ->requiredStringProperty($paramName, $description);
    }

    /**
     * Create a schema for a simple integer parameter.
     *
     * @param string $paramName The parameter name
     * @param string $description The parameter description
     * @return self
     */
    public static function integerParam(string $paramName, string $description = ''): self
    {
        return (new self())
            ->requiredIntegerProperty($paramName, $description);
    }

    /**
     * Create a schema for a simple number parameter.
     *
     * @param string $paramName The parameter name
     * @param string $description The parameter description
     * @return self
     */
    public static function numberParam(string $paramName, string $description = ''): self
    {
        return (new self())
            ->requiredNumberProperty($paramName, $description);
    }

    /**
     * Create a schema for a simple boolean parameter.
     *
     * @param string $paramName The parameter name
     * @param string $description The parameter description
     * @return self
     */
    public static function booleanParam(string $paramName, string $description = ''): self
    {
        return (new self())
            ->requiredBooleanProperty($paramName, $description);
    }
} 