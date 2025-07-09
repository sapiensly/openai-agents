<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Guardrails;

abstract class InputGuardrail
{
    /**
     * Validate the provided content.
     *
     * @throws InputGuardrailException
     */
    abstract public function validate(string $content): string;
}
