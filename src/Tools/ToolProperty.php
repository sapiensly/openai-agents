<?php

declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Tools;

/**
 * Class ToolProperty
 *
 * Represents a single property in a tool schema with strong typing support.
 * This class provides a fluent interface for building tool schema properties
 * with validation rules and constraints.
 */
class ToolProperty
{
    /**
     * The property type
     */
    private string $type;

    /**
     * The property description
     */
    private ?string $description = null;

    /**
     * Whether the property is required
     */
    private bool $required = false;

    /**
     * Minimum value for numeric types
     */
    private ?float $minimum = null;

    /**
     * Maximum value for numeric types
     */
    private ?float $maximum = null;

    /**
     * Minimum length for string types
     */
    private ?int $minLength = null;

    /**
     * Maximum length for string types
     */
    private ?int $maxLength = null;

    /**
     * Allowed values for enum types
     */
    private array $enum = [];

    /**
     * Default value
     */
    private mixed $default = null;

    /**
     * Format specification (email, uri, etc.)
     */
    private ?string $format = null;

    /**
     * Pattern for string validation (regex)
     */
    private ?string $pattern = null;

    /**
     * Create a new ToolProperty instance.
     *
     * @param string $type The property type
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Set the property description.
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
     * Mark the property as required.
     *
     * @return self
     */
    public function required(): self
    {
        $this->required = true;
        return $this;
    }

    /**
     * Set minimum value for numeric types.
     *
     * @param float $minimum The minimum value
     * @return self
     */
    public function minimum(float $minimum): self
    {
        $this->minimum = $minimum;
        return $this;
    }

    /**
     * Set maximum value for numeric types.
     *
     * @param float $maximum The maximum value
     * @return self
     */
    public function maximum(float $maximum): self
    {
        $this->maximum = $maximum;
        return $this;
    }

    /**
     * Set minimum length for string types.
     *
     * @param int $minLength The minimum length
     * @return self
     */
    public function minLength(int $minLength): self
    {
        $this->minLength = $minLength;
        return $this;
    }

    /**
     * Set maximum length for string types.
     *
     * @param int $maxLength The maximum length
     * @return self
     */
    public function maxLength(int $maxLength): self
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    /**
     * Set allowed values for enum types.
     *
     * @param array $enum The allowed values
     * @return self
     */
    public function enum(array $enum): self
    {
        $this->enum = $enum;
        return $this;
    }

    /**
     * Set default value.
     *
     * @param mixed $default The default value
     * @return self
     */
    public function default(mixed $default): self
    {
        $this->default = $default;
        return $this;
    }

    /**
     * Set format specification.
     *
     * @param string $format The format (email, uri, etc.)
     * @return self
     */
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Set pattern for string validation.
     *
     * @param string $pattern The regex pattern
     * @return self
     */
    public function pattern(string $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * Convert the property to an array representation.
     *
     * @return array The property as an array
     */
    public function toArray(): array
    {
        $property = ['type' => $this->type];

        if ($this->description !== null) {
            $property['description'] = $this->description;
        }

        if ($this->minimum !== null) {
            $property['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $property['maximum'] = $this->maximum;
        }

        if ($this->minLength !== null) {
            $property['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $property['maxLength'] = $this->maxLength;
        }

        if (!empty($this->enum)) {
            $property['enum'] = $this->enum;
        }

        if ($this->default !== null) {
            $property['default'] = $this->default;
        }

        if ($this->format !== null) {
            $property['format'] = $this->format;
        }

        if ($this->pattern !== null) {
            $property['pattern'] = $this->pattern;
        }

        return $property;
    }

    /**
     * Check if the property is required.
     *
     * @return bool True if required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get the property type.
     *
     * @return string The property type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Create a string property.
     *
     * @param string $description The description
     * @return self
     */
    public static function string(string $description = ''): self
    {
        return (new self('string'))->description($description);
    }

    /**
     * Create an integer property.
     *
     * @param string $description The description
     * @return self
     */
    public static function integer(string $description = ''): self
    {
        return (new self('integer'))->description($description);
    }

    /**
     * Create a number property.
     *
     * @param string $description The description
     * @return self
     */
    public static function number(string $description = ''): self
    {
        return (new self('number'))->description($description);
    }

    /**
     * Create a boolean property.
     *
     * @param string $description The description
     * @return self
     */
    public static function boolean(string $description = ''): self
    {
        return (new self('boolean'))->description($description);
    }

    /**
     * Create an array property.
     *
     * @param string $description The description
     * @return self
     */
    public static function array(string $description = ''): self
    {
        return (new self('array'))->description($description);
    }

    /**
     * Create an object property.
     *
     * @param string $description The description
     * @return self
     */
    public static function object(string $description = ''): self
    {
        return (new self('object'))->description($description);
    }
} 