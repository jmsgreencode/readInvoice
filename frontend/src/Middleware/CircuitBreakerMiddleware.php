<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Circuit breaker middleware for backend API calls.
 *
 * States: CLOSED (normal), OPEN (failing), HALF_OPEN (testing recovery).
 */
class CircuitBreakerMiddleware implements MiddlewareInterface
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private static string $state = self::STATE_CLOSED;
    private static int $failureCount = 0;
    private static int $lastFailureTime = 0;
    private static int $successCount = 0;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 30,
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Check if circuit is open
        if (self::$state === self::STATE_OPEN) {
            if ((time() - self::$lastFailureTime) >= $this->recoveryTimeout) {
                // Transition to half-open: allow one request through
                self::$state = self::STATE_HALF_OPEN;
                $this->logger->info('Circuit breaker transitioning to half-open');
            } else {
                $this->logger->warning('Circuit breaker is OPEN, rejecting request', [
                    'path' => $request->getUri()->getPath(),
                    'failures' => self::$failureCount,
                ]);

                return $this->buildServiceUnavailableResponse($request);
            }
        }

        try {
            $response = $handler->handle($request);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 500) {
                $this->recordFailure();
            } else {
                $this->recordSuccess();
            }

            return $response;
        } catch (\Throwable $e) {
            $this->recordFailure();

            $this->logger->error('Circuit breaker caught exception', [
                'error' => $e->getMessage(),
                'state' => self::$state,
                'failures' => self::$failureCount,
            ]);

            throw $e;
        }
    }

    private function recordFailure(): void
    {
        self::$failureCount++;
        self::$lastFailureTime = time();
        self::$successCount = 0;

        if (self::$failureCount >= $this->failureThreshold) {
            self::$state = self::STATE_OPEN;
            $this->logger->warning('Circuit breaker opened', [
                'failures' => self::$failureCount,
                'threshold' => $this->failureThreshold,
            ]);
        }
    }

    private function recordSuccess(): void
    {
        if (self::$state === self::STATE_HALF_OPEN) {
            self::$successCount++;
            // After 2 consecutive successes, close the circuit
            if (self::$successCount >= 2) {
                self::$state = self::STATE_CLOSED;
                self::$failureCount = 0;
                self::$successCount = 0;
                $this->logger->info('Circuit breaker closed after successful recovery');
            }
        } else {
            // Reset failure count on success in closed state
            self::$failureCount = 0;
        }
    }

    private function buildServiceUnavailableResponse(Request $request): Response
    {
        $accept = $request->getHeaderLine('Accept');

        $response = new SlimResponse();

        if (str_contains($accept, 'text/event-stream')) {
            $html = '<div id="toast-container"><div class="toast toast--error">'
                . '<div class="toast__content">'
                . '<div class="toast__title">Service Unavailable</div>'
                . '<div class="toast__message">The backend service is temporarily unavailable. Please try again shortly.</div>'
                . '</div></div></div>';

            $payload = "event: datastar-merge-fragments\n";
            $payload .= "data: selector #toast-container\n";
            $payload .= "data: merge morph\n";
            $payload .= "data: fragments {$html}\n\n";

            $response->getBody()->write($payload);
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'text/event-stream');
        }

        $response->getBody()->write(json_encode([
            'error' => 'Service temporarily unavailable. Please try again later.',
        ]));

        return $response
            ->withStatus(503)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string) $this->recoveryTimeout);
    }

    /**
     * Reset circuit breaker state (useful for testing).
     */
    public static function reset(): void
    {
        self::$state = self::STATE_CLOSED;
        self::$failureCount = 0;
        self::$lastFailureTime = 0;
        self::$successCount = 0;
    }
}
