<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Throwable;

/**
 * Class HandoffTimeoutException
 *
 * Exception thrown when a handoff operation times out.
 */
class HandoffTimeoutException extends HandoffException
{
    /**
     * Create a new HandoffTimeoutException instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous The previous throwable used for exception chaining
     */
    public function __construct(string $message = "Handoff operation timed out", int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
