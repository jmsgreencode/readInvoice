<?php

declare(strict_types=1);

namespace App\Exception;

class RateLimitExceededException extends AppException
{
    private int $remaining;
    private int $limit;
    private int $resetAt;

    public function __construct(string $message, int $remaining, int $limit, int $resetAt)
    {
        parent::__construct($message, 'RATE_LIMIT_EXCEEDED', 429, 'Too many requests. Please wait and try again.');
        $this->remaining = $remaining;
        $this->limit = $limit;
        $this->resetAt = $resetAt;
    }

    public function getRemaining(): int
    {
        return $this->remaining;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getResetAt(): int
    {
        return $this->resetAt;
    }
}
