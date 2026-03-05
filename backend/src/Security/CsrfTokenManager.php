<?php

declare(strict_types=1);

namespace App\Security;

class CsrfTokenManager
{
    private const TOKEN_LENGTH = 32;

    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['csrf_token'] = $token;
            $_SESSION['csrf_token_time'] = time();
        }

        return $token;
    }

    public function validateToken(string $token, int $maxAgeSeconds = 3600): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
            return false;
        }

        if ((time() - $_SESSION['csrf_token_time']) > $maxAgeSeconds) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
