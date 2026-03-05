<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Resilience\RateLimiter;
use App\Exception\RateLimitExceededException;

class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiter $rateLimiter;

    public function __construct(RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $this->getClientIp($request);
        $userId = $request->getAttribute('user_id');
        $key = $userId ? "user:{$userId}" : "ip:{$clientIp}";
        $route = $request->getUri()->getPath();

        try {
            $result = $this->rateLimiter->check($key, $route);
            $response = $handler->handle($request);

            return $response
                ->withHeader('X-RateLimit-Limit', (string)$result['limit'])
                ->withHeader('X-RateLimit-Remaining', (string)$result['remaining'])
                ->withHeader('X-RateLimit-Reset', (string)$result['reset']);
        } catch (RateLimitExceededException $e) {
            $response = new \Slim\Psr7\Response(429);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => $e->getUserMessage(),
                ],
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string)($e->getResetAt() - time()))
                ->withHeader('X-RateLimit-Limit', (string)$e->getLimit())
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string)$e->getResetAt());
        }
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ips = explode(',', $serverParams[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
