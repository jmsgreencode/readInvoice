<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Exception\AppException;
use App\Exception\ValidationException;
use App\Logging\StructuredLogger;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private StructuredLogger $logger;

    private const USER_SAFE_MESSAGES = [
        'INTERNAL_ERROR' => 'An unexpected error occurred. Please try again later.',
        'VALIDATION_ERROR' => 'Please check your input and try again.',
        'UNAUTHORIZED' => 'Authentication required.',
        'FORBIDDEN' => 'You do not have permission to perform this action.',
        'NOT_FOUND' => 'The requested resource was not found.',
        'RATE_LIMIT_EXCEEDED' => 'Too many requests. Please wait and try again.',
        'SERVICE_UNAVAILABLE' => 'Service temporarily unavailable. Please try again later.',
        'CIRCUIT_OPEN' => 'Service temporarily unavailable. Please try again later.',
    ];

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (AppException $e) {
            return $this->handleAppException($e, $request);
        } catch (\Throwable $e) {
            return $this->handleUnexpectedException($e, $request);
        }
    }

    private function handleAppException(AppException $e, ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->error($e->getMessage(), [
            'error_code' => $e->getErrorCode(),
            'http_status' => $e->getHttpStatus(),
            'path' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
        ]);

        $userRole = $request->getAttribute('user_role', 'user');
        $payload = $this->buildErrorPayload($e, $userRole, $request);

        if ($e instanceof ValidationException) {
            $payload['error']['validation_errors'] = $e->getErrors();
        }

        $response = new \Slim\Psr7\Response($e->getHttpStatus());
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function handleUnexpectedException(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $this->logger->critical('Unhandled exception', [
            'error_class' => get_class($e),
            'error_message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'path' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
        ]);

        $userRole = $request->getAttribute('user_role', 'user');
        $payload = [
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => self::USER_SAFE_MESSAGES['INTERNAL_ERROR'],
            ],
            'meta' => [
                'request_id' => $request->getAttribute('request_id', $this->logger->getRequestId()),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
        ];

        // Admin sees full technical details
        if ($userRole === 'admin') {
            $payload['error']['detail'] = $e->getMessage();
            $payload['error']['class'] = get_class($e);
            $payload['error']['file'] = $e->getFile() . ':' . $e->getLine();
            $payload['error']['trace'] = explode("\n", $e->getTraceAsString());
        }

        $response = new \Slim\Psr7\Response(500);
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function buildErrorPayload(AppException $e, string $userRole, ServerRequestInterface $request): array
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $e->getErrorCode(),
                'message' => $e->getUserMessage(),
            ],
            'meta' => [
                'request_id' => $request->getAttribute('request_id', $this->logger->getRequestId()),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
        ];

        // Admin gets full technical detail
        if ($userRole === 'admin') {
            $payload['error']['detail'] = $e->getMessage();
            $payload['error']['file'] = $e->getFile() . ':' . $e->getLine();
        }

        return $payload;
    }
}
