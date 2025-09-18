<?php

declare(strict_types=1);

namespace AGUI\Core\Validation;

use RuntimeException;

/**
 * Exception thrown when validation fails
 *
 * @package AGUI\Core\Validation
 */
final class ValidationException extends RuntimeException
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
