<?php

declare(strict_types=1);

namespace App\DTO;

class ErrorResponseDTO implements \JsonSerializable
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $detail = null,
        public readonly ?array $validationErrors = null,
        public readonly ?string $requestId = null,
    ) {}

    public function jsonSerialize(): array
    {
        $data = [
            'success' => false,
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
            ],
        ];

        if ($this->detail !== null) {
            $data['error']['detail'] = $this->detail;
        }

        if ($this->validationErrors !== null) {
            $data['error']['validation_errors'] = $this->validationErrors;
        }

        if ($this->requestId !== null) {
            $data['meta'] = [
                'request_id' => $this->requestId,
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ];
        }

        return $data;
    }
}
