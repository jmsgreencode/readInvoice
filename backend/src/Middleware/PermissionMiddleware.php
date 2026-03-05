<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PermissionMiddleware implements MiddlewareInterface
{
    private string $requiredPermission;

    public function __construct(string $requiredPermission)
    {
        $this->requiredPermission = $requiredPermission;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $permissions = $request->getAttribute('permissions', []);

        if (!in_array($this->requiredPermission, $permissions, true)) {
            $response = new \Slim\Psr7\Response(403);
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to perform this action.',
                ],
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
