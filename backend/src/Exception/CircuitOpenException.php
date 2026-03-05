<?php

declare(strict_types=1);

namespace App\Exception;

class CircuitOpenException extends AppException
{
    public function __construct(string $message = 'Service temporarily unavailable')
    {
        parent::__construct($message, 'SERVICE_UNAVAILABLE', 503, $message);
    }
}
