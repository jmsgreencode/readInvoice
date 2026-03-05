<?php

declare(strict_types=1);

namespace App\Exception;

class ValidationException extends AppException
{
    private array $errors;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message, 'VALIDATION_ERROR', 422, 'Please check your input and try again.');
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
