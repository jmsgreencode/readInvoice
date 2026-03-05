<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use ReadInvoice\Frontend\Service\BackendApiClient;
use ReadInvoice\Frontend\Service\SessionService;

class AuthPageController
{
    public function __construct(
        private readonly BackendApiClient $api,
        private readonly SessionService $session,
        private readonly LoggerInterface $logger,
    ) {}

    public function showLogin(Request $request, Response $response): Response
    {
        $this->session->start();

        if ($this->session->get('user') !== null) {
            return $response
                ->withHeader('Location', '/dashboard')
                ->withStatus(302);
        }

        $csrfToken = $this->session->getCsrfToken();
        $error = $this->session->getFlash('login_error');

        ob_start();
        $viewData = [
            'csrfToken' => $csrfToken,
            'error' => $error,
            'pageTitle' => 'Login',
        ];
        extract($viewData);
        include __DIR__ . '/../View/login.php';
        $content = ob_get_clean();

        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function handleLogin(Request $request, Response $response): Response
    {
        $this->session->start();

        $body = $request->getParsedBody();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $csrfToken = (string) ($body['csrf_token'] ?? '');

        // Validate CSRF
        if (!$this->session->validateCsrfToken($csrfToken)) {
            $this->logger->warning('CSRF token validation failed on login', [
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            ]);
            $this->session->setFlash('login_error', 'Invalid request. Please try again.');
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        // Validate input
        if ($username === '' || $password === '') {
            $this->session->setFlash('login_error', 'Username and password are required.');
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        try {
            $result = $this->api->post('/api/auth/login', [
                'username' => $username,
                'password' => $password,
            ]);

            // Backend wraps response in {success: true, data: {...}}
            $data = $result['data'] ?? $result;

            if (!empty($data['token']) && !empty($data['user'])) {
                $this->session->regenerate();

                $this->session->set('user', $data['user']);
                $this->session->set('jwt_token', $data['token']);
                $this->session->set('login_time', time());

                $this->logger->info('User logged in', [
                    'user_id' => $data['user']['id'] ?? null,
                    'username' => $username,
                ]);

                return $response->withHeader('Location', '/dashboard')->withStatus(302);
            }

            $errorMsg = $result['error']['message'] ?? $data['message'] ?? 'Invalid credentials.';
            $this->session->setFlash('login_error', $errorMsg);
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\Throwable $e) {
            $this->logger->error('Login request failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            $this->session->setFlash('login_error', 'Authentication service unavailable. Please try again later.');
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->session->start();

        $user = $this->session->get('user');
        if ($user) {
            $this->logger->info('User logged out', [
                'user_id' => $user['id'] ?? null,
            ]);
        }

        $this->session->destroy();

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
