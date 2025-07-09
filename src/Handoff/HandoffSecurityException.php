<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Throwable;

/**
 * Class HandoffSecurityException
 *
 * Exception thrown when a security violation occurs during handoff.
 * For example, when an agent tries to hand off to another agent without permission.
 */
class HandoffSecurityException extends HandoffException
{
    /**
     * Create a new HandoffSecurityException instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous The previous throwable used for exception chaining
     */
    public function __construct(string $message = "Security violation during handoff", int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
