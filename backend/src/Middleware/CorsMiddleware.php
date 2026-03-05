<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;

    public function __construct(array $allowedOrigins = [])
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response(204);
            return $this->addCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        if ($origin && ($this->isAllowedOrigin($origin) || empty($this->allowedOrigins))) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }

        return $response
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Request-Id, X-CSRF-Token')
            ->withHeader('Access-Control-Max-Age', '3600')
            ->withHeader('Access-Control-Expose-Headers', 'X-Request-Id, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');
    }

    private function isAllowedOrigin(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }
}
