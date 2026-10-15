<?php

namespace App\Exceptions;

use Exception;

class CourierTransientException extends Exception
{
    public function __construct(
        string $message,
        protected int $retryAfterSeconds = 30,
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
