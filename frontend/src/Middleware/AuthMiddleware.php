<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReadInvoice\Frontend\Service\SessionService;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionService $session,
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->session->start();

        $user = $this->session->get('user');

        if ($user === null || !$this->session->has('jwt_token')) {
            // Check if this is a Datastar SSE request
            $accept = $request->getHeaderLine('Accept');
            if (str_contains($accept, 'text/event-stream')) {
                // Return SSE redirect signal for Datastar
                $response = new SlimResponse();
                $payload = "event: datastar-execute-script\n";
                $payload .= "data: script window.location.href='/login'\n\n";
                $response->getBody()->write($payload);
                return $response
                    ->withStatus(200)
                    ->withHeader('Content-Type', 'text/event-stream');
            }

            // Standard HTTP redirect
            $response = new SlimResponse();
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        // Check session expiry (1 hour)
        $loginTime = $this->session->get('login_time', 0);
        if (time() - $loginTime > 3600) {
            $this->session->destroy();

            $response = new SlimResponse();
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        // Add user to request attributes for downstream use
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('isAdmin', ($user['role'] ?? '') === 'admin');

        return $handler->handle($request);
    }
}
