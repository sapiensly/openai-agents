<?php

namespace Sapiensly\OpenaiAgents\Traits;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionFunction;

trait FunctionSchemaGenerator
{
    /**
     * Generate JSON schema from PHP methods
     * @throws ReflectionException
     */
    private function generateFunctionSchema(string|object|array|callable $input): array
    {
        if (is_string($input) && class_exists($input)) {
            return $this->generateFromClass($input);
        }

        if (is_object($input)) {
            return $this->generateFromClass(get_class($input));
        }

        if (is_array($input)) {
            return $input; // Already a schema
        }

        if (is_callable($input)) {
            return $this->generateFromCallable($input);
        }


        throw new InvalidArgumentException('Invalid input for function schema generation');
    }

    /**
     * @throws ReflectionException
     */
    private function generateFromClass(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $schemas = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($this->shouldIncludeMethod($method)) {
                $schemas[] = $this->methodToSchema($method);
            }
        }

        return $schemas;
    }

    /**
     * Generate schema from callable
     * @throws ReflectionException
     */
    private function generateFromCallable(callable $callable): array
    {
        if (is_array($callable)) {
            // Method array format: [$object, 'methodName'] or ['ClassName', 'methodName']
            [$classOrObject, $methodName] = $callable;

            if (is_object($classOrObject)) {
                $reflection = new ReflectionMethod($classOrObject, $methodName);
            } else {
                $reflection = new ReflectionMethod($classOrObject, $methodName);
            }

            return [$this->methodToSchema($reflection)];
        }

        if (is_string($callable) && function_exists($callable)) {
            // Named function
            $reflection = new ReflectionFunction($callable);
            return [$this->functionToSchema($reflection)];
        }

        // Closure or anonymous function
        try {
            $reflection = new ReflectionFunction($callable);
            return [$this->functionToSchema($reflection)];
        } catch (\Exception $e) {
            // Fallback for complex callables
            return [[
                'type' => 'function',
                'name' => 'anonymous_function_' . uniqid(),
                'description' => 'Anonymous function',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'additionalProperties' => true
                ]
            ]];
        }
    }

    /**
     * Convert ReflectionFunction to schema
     */
    private function functionToSchema(ReflectionFunction $function): array
    {
        return [
            'type' => 'function',
            'name' => $this->camelToSnake($function->getName()),
            'description' => $this->extractFunctionDescription($function),
            'parameters' => $this->extractFunctionParameters($function)
        ];
    }

    /**
     * Extract description from ReflectionFunction
     */
    private function extractFunctionDescription(ReflectionFunction $function): string
    {
        // 1. Check docblock
        $docComment = $function->getDocComment();
        if ($docComment && preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)(?:\n|\@)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }

        // 2. Generate from function name
        $name = $function->getName();
        if (str_starts_with($name, '{closure}') || $name === 'anonymous') {
            return 'Anonymous function';
        }

        $words = preg_split('/(?=[A-Z])|_/', $name);
        $words = array_filter(array_map('trim', $words));

        if (empty($words)) {
            return "Executes {$name}";
        }

        return ucfirst(strtolower(implode(' ', $words)));
    }

    /**
     * Extract parameters from ReflectionFunction
     */
    private function extractFunctionParameters(ReflectionFunction $function): array
    {
        $parameters = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'additionalProperties' => false
        ];

        foreach ($function->getParameters() as $param) {
            $paramName = $param->getName();
            $type = $this->getParameterType($param);

            $parameters['properties'][$paramName] = [
                'type' => $type,
                'description' => $this->getFunctionParameterDescription($param, $function)
            ];

            if (!$param->isOptional() && !$param->allowsNull()) {
                $parameters['required'][] = $paramName;
            }
        }

        return $parameters;
    }

    /**
     * Get parameter description from ReflectionFunction
     */
    private function getFunctionParameterDescription(ReflectionParameter $param, ReflectionFunction $function): string
    {
        $docComment = $function->getDocComment();
        $paramName = $param->getName();

        if ($docComment && preg_match('/@param\s+\S+\s+\$' . preg_quote($paramName) . '\s+(.+?)(?:\n|\*\/)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return "The {$paramName} parameter";
    }

    private function shouldIncludeMethod(ReflectionMethod $method): bool
    {
        $name = $method->getName();
        return !str_starts_with($name, '__') &&
               !in_array($name, ['getClient', 'getId', 'getMessages']) &&
               !$method->isStatic() &&
               !$method->isAbstract();
    }

    private function methodToSchema(ReflectionMethod $method): array
    {
        return [
            'type' => 'function',
            'name' => $this->camelToSnake($method->getName()),
            'description' => $this->extractDescription($method),
            'parameters' => $this->extractParameters($method)
        ];
    }

    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Convert snake_case to camelCase
     */
    private function snakeToCamel(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }


    private function extractDescription(ReflectionMethod $method): string
    {
        // 1. Check docblock
        $docComment = $method->getDocComment();
        if ($docComment && preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)(?:\n|\@)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }

        // 2. Generate from method name
        $name = $method->getName();
        $words = preg_split('/(?=[A-Z])/', $name);
        $words = array_filter(array_map('trim', $words));

        if (empty($words)) {
            return "Executes {$name}";
        }

        return ucfirst(strtolower(implode(' ', $words)));
    }

    private function extractParameters(ReflectionMethod $method): array
    {
        $parameters = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'additionalProperties' => false
        ];

        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $type = $this->getParameterType($param);

            $parameters['properties'][$paramName] = [
                'type' => $type,
                'description' => $this->getParameterDescription($param, $method)
            ];

            if (!$param->isOptional() && !$param->allowsNull()) {
                $parameters['required'][] = $paramName;
            }
        }

        return $parameters;
    }

    private function getParameterType(ReflectionParameter $param): string
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType) {
            return 'string';
        }

        return match($type->getName()) {
            'string' => 'string',
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string'
        };
    }

    private function getParameterDescription(ReflectionParameter $param, ReflectionMethod $method): string
    {
        $docComment = $method->getDocComment();
        $paramName = $param->getName();

        if ($docComment && preg_match('/@param\s+\S+\s+\$' . preg_quote($paramName) . '\s+(.+?)(?:\n|\*\/)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return "The {$paramName} parameter";
    }

    /**
     * Validate function schema structure
     */
    private function isValidFunctionSchema(array $schema): bool
    {
        return isset($schema['type']) &&
            $schema['type'] === 'function' &&
            isset($schema['name']) &&
            isset($schema['description']);
    }
}
