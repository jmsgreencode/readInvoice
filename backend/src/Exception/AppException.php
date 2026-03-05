<?php

declare(strict_types=1);

namespace App\Exception;

class AppException extends \RuntimeException
{
    private string $errorCode;
    private int $httpStatus;
    private string $userMessage;

    public function __construct(
        string $message,
        string $errorCode = 'INTERNAL_ERROR',
        int $httpStatus = 500,
        string $userMessage = 'An unexpected error occurred. Please try again later.',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->userMessage = $userMessage;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}
