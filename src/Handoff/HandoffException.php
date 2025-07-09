<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Exception;
use Throwable;

/**
 * Class HandoffException
 *
 * Base exception class for handoff-related errors.
 */
class HandoffException extends Exception
{
    /**
     * Create a new HandoffException instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous The previous throwable used for exception chaining
     */
public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
