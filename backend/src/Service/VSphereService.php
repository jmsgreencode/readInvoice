<?php

declare(strict_types=1);

namespace App\Service;

use App\Resilience\CircuitBreaker;
use App\Logging\StructuredLogger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class VSphereService
{
    private Client $http;
    private CircuitBreaker $circuitBreaker;
    private StructuredLogger $logger;
    private string $baseUrl;
    private string $user;
    private string $pass;
    private bool $sslVerify;
    private ?string $sessionId = null;

    public function __construct(
        CircuitBreaker $circuitBreaker,
        StructuredLogger $logger,
        string $baseUrl,
        string $user,
        string $pass,
        bool $sslVerify = true
    ) {
        $this->circuitBreaker = $circuitBreaker;
        $this->logger = $logger;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->user = $user;
        $this->pass = $pass;
        $this->sslVerify = $sslVerify;

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => $this->sslVerify,
        ]);
    }

    public function authenticate(): void
    {
        $response = $this->circuitBreaker->call('vsphere_api', function () {
            return $this->http->post('/api/session', [
                RequestOptions::AUTH => [$this->user, $this->pass],
                RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
            ]);
        });

        $this->sessionId = json_decode($response->getBody()->getContents(), true);

        $this->logger->info('vSphere session established');
    }

    public function listVMs(array $filters = []): array
    {
        $this->ensureAuthenticated();

        return $this->circuitBreaker->call('vsphere_api', function () use ($filters) {
            $response = $this->http->get('/api/vcenter/vm', [
                RequestOptions::HEADERS => $this->getHeaders(),
                RequestOptions::QUERY => $filters,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        });
    }

    public function getVM(string $vmId): array
    {
        $this->ensureAuthenticated();

        return $this->circuitBreaker->call('vsphere_api', function () use ($vmId) {
            $response = $this->http->get("/api/vcenter/vm/{$vmId}", [
                RequestOptions::HEADERS => $this->getHeaders(),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        });
    }

    public function listDatastores(): array
    {
        $this->ensureAuthenticated();

        return $this->circuitBreaker->call('vsphere_api', function () {
            $response = $this->http->get('/api/vcenter/datastore', [
                RequestOptions::HEADERS => $this->getHeaders(),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        });
    }

    public function vmPowerAction(string $vmId, string $action): void
    {
        $allowedActions = ['start', 'stop', 'reset', 'suspend'];
        if (!in_array($action, $allowedActions, true)) {
            throw new \InvalidArgumentException("Invalid power action: {$action}");
        }

        $this->ensureAuthenticated();

        $this->circuitBreaker->call('vsphere_api', function () use ($vmId, $action) {
            $this->http->post("/api/vcenter/vm/{$vmId}/power/{$action}", [
                RequestOptions::HEADERS => $this->getHeaders(),
            ]);
        });

        $this->logger->info('vSphere VM power action', ['vm_id' => $vmId, 'action' => $action]);
    }

    private function ensureAuthenticated(): void
    {
        if ($this->sessionId === null) {
            $this->authenticate();
        }
    }

    private function getHeaders(): array
    {
        return [
            'vmware-api-session-id' => $this->sessionId,
            'Content-Type' => 'application/json',
        ];
    }
}
