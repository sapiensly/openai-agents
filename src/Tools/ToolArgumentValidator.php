<?php

namespace Sapiensly\OpenaiAgents\Tools;

class ToolArgumentValidator
{
    /**
     * Validates arguments against the tool's JSON Schema.
     *
     * @param array $args Arguments to validate
     * @param array $schema JSON Schema
     * @return array List of errors (empty if all valid)
     */
    public static function validate(array $args, array $schema): array
    {
        $errors = [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        // Validate required fields
        foreach ($required as $field) {
            if (!array_key_exists($field, $args)) {
                $errors[] = "Missing required argument: '$field'";
            }
        }

        // Validate each property
        foreach ($properties as $key => $propSchema) {
            if (!array_key_exists($key, $args)) {
                continue; // Already validated required
            }
            $value = $args[$key];
            $type = $propSchema['type'] ?? 'string';
            if (!self::validateType($value, $type)) {
                $errors[] = "Argument '$key' must be of type $type";
                continue;
            }
            // Validate enum
            if (isset($propSchema['enum']) && !in_array($value, $propSchema['enum'], true)) {
                $errors[] = "Argument '$key' must be one of: " . implode(', ', $propSchema['enum']);
            }
            // Validate min/max
            if (isset($propSchema['minimum']) && $value < $propSchema['minimum']) {
                $errors[] = "Argument '$key' must be >= {$propSchema['minimum']}";
            }
            if (isset($propSchema['maximum']) && $value > $propSchema['maximum']) {
                $errors[] = "Argument '$key' must be <= {$propSchema['maximum']}";
            }
            // Validate minLength/maxLength
            if (isset($propSchema['minLength']) && is_string($value) && mb_strlen($value) < $propSchema['minLength']) {
                $errors[] = "Argument '$key' must have at least {$propSchema['minLength']} characters";
            }
            if (isset($propSchema['maxLength']) && is_string($value) && mb_strlen($value) > $propSchema['maxLength']) {
                $errors[] = "Argument '$key' must have at most {$propSchema['maxLength']} characters";
            }
        }
        return $errors;
    }

    private static function validateType($value, $type): bool
    {
        return match($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            default => true,
        };
    }
} 