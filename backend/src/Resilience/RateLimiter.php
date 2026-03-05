<?php

declare(strict_types=1);

namespace App\Resilience;

use App\Database\Connection;
use App\Exception\RateLimitExceededException;
use App\Logging\StructuredLogger;

/**
 * Token bucket rate limiter backed by MySQL.
 */
class RateLimiter
{
    private Connection $db;
    private StructuredLogger $logger;
    private array $config;

    public function __construct(Connection $db, StructuredLogger $logger, array $config)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Check and consume a rate limit token.
     *
     * @return array{allowed: bool, remaining: int, limit: int, reset: int}
     * @throws RateLimitExceededException
     */
    public function check(string $key, ?string $route = null): array
    {
        $limits = $this->getLimitsForRoute($route);
        $maxRequests = $limits['max_requests'];
        $windowSeconds = $limits['window_seconds'];
        $rateKey = $this->buildKey($key, $route);

        $now = microtime(true);
        $windowStart = $now - $windowSeconds;

        // Clean expired entries
        $this->db->execute(
            'DELETE FROM rate_limits WHERE rate_key = ? AND last_refill_at < ?',
            [$rateKey, date('Y-m-d H:i:s.u', (int)$windowStart)]
        );

        // Count current requests in window
        $stmt = $this->db->execute(
            'SELECT COUNT(*) as cnt FROM rate_limits WHERE rate_key = ? AND last_refill_at >= ?',
            [$rateKey, date('Y-m-d H:i:s.u', (int)$windowStart)]
        );
        $count = (int)$stmt->fetch()['cnt'];

        $remaining = max(0, $maxRequests - $count);
        $reset = (int)($windowStart + $windowSeconds);

        if ($count >= $maxRequests) {
            $this->logger->warning('Rate limit exceeded', [
                'rate_key' => $rateKey,
                'count' => $count,
                'limit' => $maxRequests,
                'window_seconds' => $windowSeconds,
            ]);

            throw new RateLimitExceededException(
                'Too many requests. Please try again later.',
                $remaining,
                $maxRequests,
                $reset
            );
        }

        // Record this request
        $this->db->execute(
            'INSERT INTO rate_limits (rate_key, tokens, last_refill_at, expires_at) VALUES (?, 1, NOW(6), ?)',
            [$rateKey, date('Y-m-d H:i:s', $reset)]
        );

        return [
            'allowed' => true,
            'remaining' => $remaining - 1,
            'limit' => $maxRequests,
            'reset' => $reset,
        ];
    }

    public function getHeaders(string $key, ?string $route = null): array
    {
        $limits = $this->getLimitsForRoute($route);
        $rateKey = $this->buildKey($key, $route);
        $windowSeconds = $limits['window_seconds'];
        $windowStart = time() - $windowSeconds;

        $stmt = $this->db->execute(
            'SELECT COUNT(*) as cnt FROM rate_limits WHERE rate_key = ? AND last_refill_at >= ?',
            [$rateKey, date('Y-m-d H:i:s', $windowStart)]
        );
        $count = (int)$stmt->fetch()['cnt'];

        return [
            'X-RateLimit-Limit' => (string)$limits['max_requests'],
            'X-RateLimit-Remaining' => (string)max(0, $limits['max_requests'] - $count),
            'X-RateLimit-Reset' => (string)(time() + $windowSeconds),
        ];
    }

    private function getLimitsForRoute(?string $route): array
    {
        if ($route && isset($this->config['routes'])) {
            foreach ($this->config['routes'] as $pattern => $limits) {
                if (fnmatch($pattern, $route)) {
                    return $limits;
                }
            }
        }

        return $this->config['default'] ?? [
            'max_requests' => 100,
            'window_seconds' => 60,
        ];
    }

    private function buildKey(string $key, ?string $route): string
    {
        return $route ? "{$key}:{$route}" : $key;
    }
}
