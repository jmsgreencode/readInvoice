<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\Connection;
use App\Exception\AppException;
use App\Logging\StructuredLogger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    private Connection $db;
    private StructuredLogger $logger;
    private string $jwtSecret;
    private int $tokenExpiry;
    private ?RbacService $rbacService = null;

    public function __construct(Connection $db, StructuredLogger $logger, string $jwtSecret, int $tokenExpiry = 3600)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->jwtSecret = $jwtSecret;
        $this->tokenExpiry = $tokenExpiry;
    }

    public function setRbacService(RbacService $rbacService): void
    {
        $this->rbacService = $rbacService;
    }

    public function login(string $username, string $password): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM users WHERE username = ? AND is_active = 1',
            [$username]
        );
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logger->warning('Failed login attempt', ['username' => $username]);
            throw new AppException(
                'Invalid credentials',
                'UNAUTHORIZED',
                401,
                'Invalid username or password.'
            );
        }

        $this->db->execute(
            'UPDATE users SET last_login_at = NOW() WHERE id = ?',
            [$user['id']]
        );

        $roles = [];
        $permissions = [];
        if ($this->rbacService) {
            $userRoles = $this->rbacService->getUserRoles((int)$user['id']);
            $roles = array_column($userRoles, 'name');
            $permissions = $this->rbacService->getUserPermissions((int)$user['id']);
        }

        $token = $this->generateToken($user, $roles, $permissions);
        $refreshToken = $this->generateRefreshToken($user);

        $this->logger->info('User logged in', ['user_id' => $user['id'], 'roles' => $roles]);

        return [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->tokenExpiry,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        ];
    }

    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return (array)$decoded;
        } catch (\Exception $e) {
            throw new AppException(
                'Token validation failed: ' . $e->getMessage(),
                'UNAUTHORIZED',
                401,
                'Your session has expired. Please log in again.'
            );
        }
    }

    public function refreshToken(string $refreshToken): array
    {
        $decoded = $this->validateToken($refreshToken);

        if (($decoded['type'] ?? '') !== 'refresh') {
            throw new AppException('Invalid refresh token', 'UNAUTHORIZED', 401, 'Please log in again.');
        }

        $stmt = $this->db->execute(
            'SELECT * FROM users WHERE id = ? AND is_active = 1',
            [$decoded['id']]
        );
        $user = $stmt->fetch();

        if (!$user) {
            throw new AppException('User not found', 'UNAUTHORIZED', 401, 'Please log in again.');
        }

        $roles = [];
        $permissions = [];
        if ($this->rbacService) {
            $userRoles = $this->rbacService->getUserRoles((int)$user['id']);
            $roles = array_column($userRoles, 'name');
            $permissions = $this->rbacService->getUserPermissions((int)$user['id']);
        }

        return [
            'token' => $this->generateToken($user, $roles, $permissions),
            'refresh_token' => $this->generateRefreshToken($user),
            'expires_in' => $this->tokenExpiry,
        ];
    }

    private function generateToken(array $user, array $roles = [], array $permissions = []): string
    {
        $payload = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'roles' => $roles,
            'permissions' => $permissions,
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + $this->tokenExpiry,
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    private function generateRefreshToken(array $user): string
    {
        $payload = [
            'id' => $user['id'],
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + ($this->tokenExpiry * 24),
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }
}
