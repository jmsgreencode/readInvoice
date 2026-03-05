<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Service\AuthService;
use App\Logging\StructuredLogger;

class AuthMiddleware implements MiddlewareInterface
{
    private AuthService $authService;
    private StructuredLogger $logger;
    private array $publicRoutes;

    public function __construct(AuthService $authService, StructuredLogger $logger, array $publicRoutes = [])
    {
        $this->authService = $authService;
        $this->logger = $logger;
        $this->publicRoutes = $publicRoutes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        foreach ($this->publicRoutes as $route) {
            if (str_starts_with($path, $route)) {
                return $handler->handle($request);
            }
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Missing or invalid authorization header');
        }

        $token = substr($authHeader, 7);

        try {
            $user = $this->authService->validateToken($token);
            $request = $request->withAttribute('user', $user);
            $request = $request->withAttribute('user_id', $user['id']);
            $request = $request->withAttribute('user_role', $user['role']);

            $this->logger->info('Request authenticated', [
                'user_id' => $user['id'],
                'role' => $user['role'],
                'path' => $path,
            ]);

            return $handler->handle($request);
        } catch (\Exception $e) {
            $this->logger->warning('Authentication failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return $this->unauthorizedResponse('Invalid or expired token');
        }
    }

    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new \Slim\Psr7\Response(401);
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Authentication required',
            ],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
