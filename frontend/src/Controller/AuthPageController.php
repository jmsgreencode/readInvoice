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

    /**
     * Render the login page.
     */
    public function showLogin(Request $request, Response $response): Response
    {
        $this->session->start();

        // Already logged in? Redirect to dashboard.
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

    /**
     * Handle login form submission.
     */
    public function handleLogin(Request $request, Response $response): Response
    {
        $this->session->start();

        $body = $request->getParsedBody();
        $email = trim((string) ($body['email'] ?? ''));
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
        if ($email === '' || $password === '') {
            $this->session->setFlash('login_error', 'Email and password are required.');
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->setFlash('login_error', 'Please enter a valid email address.');
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        try {
            $result = $this->api->post('/api/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

            if (!empty($result['token']) && !empty($result['user'])) {
                // Regenerate session ID to prevent fixation
                $this->session->regenerate();

                $this->session->set('user', $result['user']);
                $this->session->set('jwt_token', $result['token']);
                $this->session->set('login_time', time());

                $this->logger->info('User logged in', [
                    'user_id' => $result['user']['id'] ?? null,
                    'email' => $email,
                ]);

                return $response->withHeader('Location', '/dashboard')->withStatus(302);
            }

            $this->session->setFlash('login_error', $result['message'] ?? 'Invalid credentials.');
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\Throwable $e) {
            $this->logger->error('Login request failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $this->session->setFlash('login_error', 'Authentication service unavailable. Please try again later.');
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
    }

    /**
     * Log out the current user.
     */
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
