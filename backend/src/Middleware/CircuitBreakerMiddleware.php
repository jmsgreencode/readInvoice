<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Resilience\CircuitBreaker;
use App\Exception\CircuitOpenException;

class CircuitBreakerMiddleware implements MiddlewareInterface
{
    private CircuitBreaker $circuitBreaker;
    private string $serviceName;

    public function __construct(CircuitBreaker $circuitBreaker, string $serviceName = 'backend')
    {
        $this->circuitBreaker = $circuitBreaker;
        $this->serviceName = $serviceName;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $this->circuitBreaker->call($this->serviceName, function () use ($request, $handler) {
                return $handler->handle($request);
            });
        } catch (CircuitOpenException $e) {
            $response = new \Slim\Psr7\Response(503);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => [
                    'code' => 'SERVICE_UNAVAILABLE',
                    'message' => 'Service temporarily unavailable. Please try again later.',
                ],
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', '30');
        }
    }
}
