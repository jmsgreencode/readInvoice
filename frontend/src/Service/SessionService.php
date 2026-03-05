<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Service;

/**
 * PHP session management with CSRF token generation and validation.
 */
class SessionService
{
    private bool $started = false;

    /**
     * Start the session if not already started.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        // Secure session configuration
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_name('READINVOICE_SESSID');
        session_start();

        $this->started = true;
    }

    /**
     * Get a session value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a session value.
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Check if a session key exists.
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Regenerate session ID (prevents session fixation).
     */
    public function regenerate(): void
    {
        $this->ensureStarted();
        session_regenerate_id(true);
    }

    /**
     * Destroy the session entirely.
     */
    public function destroy(): void
    {
        $this->ensureStarted();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->started = false;
    }

    /**
     * Generate or retrieve the CSRF token for the current session.
     */
    public function getCsrfToken(): string
    {
        $this->ensureStarted();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a CSRF token against the session token.
     */
    public function validateCsrfToken(string $token): bool
    {
        $this->ensureStarted();

        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if ($sessionToken === '' || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Set a flash message (available only for the next request).
     */
    public function setFlash(string $key, string $message): void
    {
        $this->ensureStarted();
        $_SESSION['_flash'][$key] = $message;
    }

    /**
     * Get and remove a flash message.
     */
    public function getFlash(string $key): ?string
    {
        $this->ensureStarted();
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Ensure the session is started before operations.
     */
    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }
}
