<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

/**
 * Class ValidationResult
 *
 * Represents the result of a handoff validation operation.
 * Contains information about validation success, errors, and warnings.
 */
class ValidationResult
{
    /**
     * Create a new ValidationResult instance.
     *
     * @param bool $isValid Whether the validation passed
     * @param array $errors Array of validation errors
     * @param array $warnings Array of validation warnings
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = [],
        public readonly array $warnings = []
    ) {}

    /**
     * Check if the validation was successful.
     *
     * @return bool True if validation passed
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Check if there are any errors.
     *
     * @return bool True if there are errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any warnings.
     *
     * @return bool True if there are warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get the first error message.
     *
     * @return string|null The first error message or null if no errors
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get all error messages as a single string.
     *
     * @return string All error messages joined with newlines
     */
    public function getErrorsAsString(): string
    {
        return implode("\n", $this->errors);
    }

    /**
     * Get all warning messages as a single string.
     *
     * @return string All warning messages joined with newlines
     */
    public function getWarningsAsString(): string
    {
        return implode("\n", $this->warnings);
    }

    /**
     * Get a summary of the validation result.
     *
     * @return string A summary string
     */
    public function getSummary(): string
    {
        if ($this->isValid) {
            $summary = "Validation passed";
            if ($this->hasWarnings()) {
                $summary .= " with " . count($this->warnings) . " warning(s)";
            }
            return $summary;
        } else {
            return "Validation failed with " . count($this->errors) . " error(s)";
        }
    }
} 