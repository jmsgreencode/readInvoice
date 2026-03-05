<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Simple in-memory rate limiting for frontend routes.
 *
 * Uses APCu if available, falls back to file-based tracking.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private string $storePath;

    public function __construct(
        private readonly int $maxRequests = 60,
        private readonly int $windowSeconds = 60,
    ) {
        $this->storePath = sys_get_temp_dir() . '/readinvoice_ratelimit';
        if (!is_dir($this->storePath)) {
            @mkdir($this->storePath, 0700, true);
        }
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $clientIp = $this->getClientIp($request);
        $key = 'rl_' . md5($clientIp . $request->getUri()->getPath());

        $current = $this->getRequestCount($key);

        if ($current >= $this->maxRequests) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'error' => 'Too many requests. Please slow down.',
            ]));

            return $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) $this->windowSeconds);
        }

        $this->incrementRequestCount($key);

        return $handler->handle($request);
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        // Check forwarded headers (trusted proxy only)
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            $ips = array_map('trim', explode(',', $forwarded));
            return $ips[0];
        }

        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function getRequestCount(string $key): int
    {
        // Try APCu first
        if (function_exists('apcu_fetch')) {
            $count = apcu_fetch($key);
            return $count === false ? 0 : (int) $count;
        }

        // File-based fallback
        $file = $this->storePath . '/' . $key;
        if (!file_exists($file)) {
            return 0;
        }

        $data = json_decode(file_get_contents($file), true);
        if ($data === null || (time() - $data['start']) > $this->windowSeconds) {
            @unlink($file);
            return 0;
        }

        return (int) $data['count'];
    }

    private function incrementRequestCount(string $key): void
    {
        // Try APCu first
        if (function_exists('apcu_fetch')) {
            if (!apcu_exists($key)) {
                apcu_store($key, 1, $this->windowSeconds);
            } else {
                apcu_inc($key);
            }
            return;
        }

        // File-based fallback
        $file = $this->storePath . '/' . $key;
        $data = ['count' => 1, 'start' => time()];

        if (file_exists($file)) {
            $existing = json_decode(file_get_contents($file), true);
            if ($existing !== null && (time() - $existing['start']) <= $this->windowSeconds) {
                $data = ['count' => $existing['count'] + 1, 'start' => $existing['start']];
            }
        }

        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
