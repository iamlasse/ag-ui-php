<?php

declare(strict_types=1);

namespace AGUI\Encoder;

use Exception;

/**
 * Exception thrown when event decoding fails
 *
 * @package AGUI\Encoder
 */
class DecodingException extends Exception
{
    /**
     * @param string $message Error message
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
