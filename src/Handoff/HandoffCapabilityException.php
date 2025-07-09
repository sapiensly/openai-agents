<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Throwable;

/**
 * Class HandoffCapabilityException
 *
 * Exception thrown when a target agent does not have the required capabilities.
 */
class HandoffCapabilityException extends HandoffException
{
    /**
     * Create a new HandoffCapabilityException instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous The previous throwable used for exception chaining
     */
    public function __construct(string $message = "Target agent does not have required capabilities", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
