<?php

declare(strict_types=1);

namespace AGUI\Core;

/**
 * Base AG-UI error class
 *
 * @package AGUI\Core
 */
final class AGUIError extends \Exception
{
    /**
     * @param string $message The error message
     * @param int $code The error code
     * @param \Throwable|null $previous The previous throwable
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
