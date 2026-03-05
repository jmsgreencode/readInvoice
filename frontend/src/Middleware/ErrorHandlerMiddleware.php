<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use ReadInvoice\Frontend\Service\SessionService;
use Slim\Psr7\Response as SlimResponse;

/**
 * Global error handler middleware.
 *
 * Shows user-friendly toast for regular users, full detail for admins.
 */
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SessionService $session,
        private readonly bool $debug = false,
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    private function handleException(Request $request, \Throwable $e): Response
    {
        $statusCode = $this->resolveStatusCode($e);

        $this->logger->error('Unhandled exception', [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'path' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
        ]);

        $this->session->start();
        $user = $this->session->get('user');
        $isAdmin = ($user['role'] ?? '') === 'admin';

        $accept = $request->getHeaderLine('Accept');

        // SSE response for Datastar requests
        if (str_contains($accept, 'text/event-stream')) {
            return $this->buildSseErrorResponse($e, $isAdmin);
        }

        // JSON response for API-like requests
        if (str_contains($accept, 'application/json')) {
            return $this->buildJsonErrorResponse($e, $isAdmin, $statusCode);
        }

        // HTML response for page requests
        return $this->buildHtmlErrorResponse($e, $isAdmin, $statusCode);
    }

    private function buildSseErrorResponse(\Throwable $e, bool $isAdmin): Response
    {
        $response = new SlimResponse();

        $title = 'Something went wrong';
        $message = 'An unexpected error occurred. Please try again.';

        if ($isAdmin || $this->debug) {
            $title = get_class($e);
            $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $message .= ' (in ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        }

        $html = '<div id="toast-container">'
            . '<div class="toast toast--error">'
            . '<div class="toast__content">'
            . '<div class="toast__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div class="toast__message">' . $message . '</div>'
            . '</div>'
            . '<button class="toast__close" onclick="this.parentElement.remove()">&times;</button>'
            . '</div></div>';

        $payload = "event: datastar-merge-fragments\n";
        $payload .= "data: selector #toast-container\n";
        $payload .= "data: merge morph\n";
        $payload .= "data: fragments {$html}\n\n";

        $response->getBody()->write($payload);

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache');
    }

    private function buildJsonErrorResponse(\Throwable $e, bool $isAdmin, int $statusCode): Response
    {
        $response = new SlimResponse();

        $body = [
            'error' => 'An unexpected error occurred.',
        ];

        if ($isAdmin || $this->debug) {
            $body = [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        $response->getBody()->write(json_encode($body));

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }

    private function buildHtmlErrorResponse(\Throwable $e, bool $isAdmin, int $statusCode): Response
    {
        $response = new SlimResponse();

        $title = 'Error';
        $message = 'An unexpected error occurred. Please try again or contact support.';
        $detail = '';

        if ($isAdmin || $this->debug) {
            $title = get_class($e);
            $message = $e->getMessage();
            $detail = '<pre style="margin-top:1rem;padding:1rem;background:#f3f4f6;border-radius:4px;overflow:auto;font-size:0.8rem;">'
                . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
                . '</pre>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title>'
            . '<link rel="stylesheet" href="/assets/css/app.css"></head>'
            . '<body><div class="login-container"><div class="card login-card">'
            . '<div class="card__header"><div class="card__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div></div>'
            . '<div class="card__body">'
            . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . $detail
            . '<p style="margin-top:1rem;"><a href="/dashboard" class="btn btn--primary">Go to Dashboard</a></p>'
            . '</div></div></div></body></html>';

        $response->getBody()->write($html);

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function resolveStatusCode(\Throwable $e): int
    {
        $code = $e->getCode();
        if (is_int($code) && $code >= 400 && $code < 600) {
            return $code;
        }
        return 500;
    }
}
