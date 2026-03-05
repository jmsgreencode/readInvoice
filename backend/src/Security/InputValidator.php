<?php

declare(strict_types=1);

namespace App\Security;

use App\Exception\ValidationException;

class InputValidator
{
    public static function validateEmail(string $email): string
    {
        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            throw new ValidationException(['email' => 'Invalid email address']);
        }
        return $email;
    }

    public static function validateString(string $value, string $field, int $minLen = 1, int $maxLen = 255): string
    {
        $value = trim($value);
        $len = mb_strlen($value);

        if ($len < $minLen || $len > $maxLen) {
            throw new ValidationException([
                $field => "Must be between {$minLen} and {$maxLen} characters"
            ]);
        }

        return $value;
    }

    public static function validateId(mixed $value, string $field = 'id'): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new ValidationException([$field => 'Invalid ID']);
        }
        return $id;
    }

    public static function validateDate(string $value, string $field): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new ValidationException([$field => 'Invalid date format (expected YYYY-MM-DD)']);
        }
        return $value;
    }

    public static function validatePagination(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 25)));

        return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
    }

    public static function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);
        return substr($filename, 0, 255);
    }

    public static function validateFileUpload(array $file, array $allowedTypes, int $maxSizeBytes): void
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => 'File upload failed']);
        }

        if ($file['size'] > $maxSizeBytes) {
            $maxMb = round($maxSizeBytes / 1024 / 1024, 1);
            throw new ValidationException(['file' => "File exceeds maximum size of {$maxMb}MB"]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new ValidationException(['file' => 'File type not allowed']);
        }
    }
}
