<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Logging\StructuredLogger;

class RequestIdMiddleware implements MiddlewareInterface
{
    private StructuredLogger $logger;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine('X-Request-Id') ?: $this->logger->getRequestId();
        $request = $request->withAttribute('request_id', $requestId);

        $response = $handler->handle($request);
        return $response->withHeader('X-Request-Id', $requestId);
    }
}
