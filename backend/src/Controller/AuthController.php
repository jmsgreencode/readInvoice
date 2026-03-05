<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\AuthService;
use App\Security\InputValidator;

class AuthController
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $username = InputValidator::validateString($body['username'] ?? '', 'username', 1, 100);
        $password = InputValidator::validateString($body['password'] ?? '', 'password', 1, 255);

        $result = $this->authService->login($username, $password);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $result,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function refresh(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $refreshToken = InputValidator::validateString($body['refresh_token'] ?? '', 'refresh_token');

        $result = $this->authService->refreshToken($refreshToken);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $result,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
