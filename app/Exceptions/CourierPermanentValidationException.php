<?php

namespace App\Exceptions;

use Exception;

class CourierPermanentValidationException extends Exception
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $message,
        protected array $payload = [],
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
