<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use ReadInvoice\Frontend\Service\SessionService;

class DashboardController
{
    public function __construct(
        private readonly SessionService $session,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $this->session->start();
        $user = $this->session->get('user');
        $csrfToken = $this->session->getCsrfToken();
        $isAdmin = ($user['role'] ?? '') === 'admin';

        ob_start();
        $viewData = [
            'user' => $user,
            'csrfToken' => $csrfToken,
            'isAdmin' => $isAdmin,
            'pageTitle' => 'Dashboard',
        ];
        extract($viewData);
        include __DIR__ . '/../View/dashboard.php';
        $content = ob_get_clean();

        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
