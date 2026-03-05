<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Service;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class BackendApiClient
{
    public function __construct(
        private readonly GuzzleClient $client,
        private readonly LoggerInterface $logger,
        private readonly SessionService $session,
    ) {}

    /**
     * Make a GET request to the backend API.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    /**
     * Make a POST request to the backend API.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, ['json' => $data]);
    }

    /**
     * Make a PUT request to the backend API.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, ['json' => $data]);
    }

    /**
     * Make a DELETE request to the backend API.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Get raw response (for file downloads).
     *
     * @return array{body: string, content_type: string, content_disposition: string}
     * @throws \RuntimeException
     */
    public function getRaw(string $path): array
    {
        $options = $this->buildOptions([]);
        $options['headers']['Accept'] = '*/*';

        try {
            $response = $this->client->request('GET', $path, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                throw new \RuntimeException(
                    "Backend API returned HTTP {$statusCode} for GET {$path}"
                );
            }

            return [
                'body' => (string) $response->getBody(),
                'content_type' => $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
                'content_disposition' => $response->getHeaderLine('Content-Disposition') ?: '',
            ];
        } catch (ConnectException $e) {
            $this->logger->error('Backend connection failed (raw)', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Backend service is unavailable', 503, $e);
        }
    }

    /**
     * Core request method with structured error handling.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $options = $this->buildOptions($options);
        $requestId = $this->generateRequestId();

        $this->logger->debug('Backend API request', [
            'request_id' => $requestId,
            'method' => $method,
            'path' => $path,
        ]);

        try {
            $response = $this->client->request($method, $path, $options);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            $this->logger->debug('Backend API response', [
                'request_id' => $requestId,
                'status' => $statusCode,
                'path' => $path,
            ]);

            $decoded = json_decode($body, true);

            if ($statusCode === 401 && $this->session->has('jwt_token')) {
                // JWT expired or invalid -- clear session (not during login)
                $this->session->remove('jwt_token');
            }

            // For 4xx errors, return the decoded body so callers can handle
            // (e.g., login controller checks for error messages)
            if ($decoded !== null) {
                return $decoded;
            }

            if ($statusCode >= 400) {
                throw new \RuntimeException("Backend API returned HTTP {$statusCode}", $statusCode);
            }

            return $decoded ?? [];
        } catch (ConnectException $e) {
            $this->logger->error('Backend connection failed', [
                'request_id' => $requestId,
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Backend service is unavailable. Please try again later.', 503, $e);
        } catch (RequestException $e) {
            $this->logger->error('Backend request failed', [
                'request_id' => $requestId,
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to communicate with backend service.', 502, $e);
        }
    }

    /**
     * Build request options with JWT auth header.
     */
    private function buildOptions(array $options): array
    {
        $this->session->start();
        $token = $this->session->get('jwt_token');

        if ($token !== null) {
            $options['headers'] = $options['headers'] ?? [];
            $options['headers']['Authorization'] = "Bearer {$token}";
        }

        return $options;
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
