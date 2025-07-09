<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Guardrails;

abstract class OutputGuardrail
{
    /**
     * Validate the provided content.
     *
     * @throws OutputGuardrailException
     */
    abstract public function validate(string $content): string;
}
