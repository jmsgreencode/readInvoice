<?php

declare(strict_types=1);

namespace App\Resilience;

use App\Database\Connection;
use App\Exception\CircuitOpenException;
use App\Logging\StructuredLogger;

class CircuitBreaker
{
    private Connection $db;
    private StructuredLogger $logger;
    private array $config;

    public function __construct(Connection $db, StructuredLogger $logger, array $config)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Execute a callable with circuit breaker protection.
     *
     * @throws CircuitOpenException
     */
    public function call(string $serviceName, callable $operation): mixed
    {
        $state = $this->getState($serviceName);
        $serviceConfig = $this->getServiceConfig($serviceName);

        if ($state['state'] === 'open') {
            $openedAt = strtotime($state['opened_at']);
            $recoveryTimeout = $serviceConfig['recovery_timeout'];

            if ((time() - $openedAt) >= $recoveryTimeout) {
                $this->transitionTo($serviceName, 'half_open');
                $state['state'] = 'half_open';
            } else {
                $this->logger->warning('Circuit breaker open, rejecting call', [
                    'circuit_service' => $serviceName,
                    'state' => 'open',
                    'opened_at' => $state['opened_at'],
                ]);
                throw new CircuitOpenException(
                    "Service '{$serviceName}' is temporarily unavailable. Please try again later."
                );
            }
        }

        try {
            $result = $operation();
            $this->onSuccess($serviceName, $state, $serviceConfig);
            return $result;
        } catch (\Exception $e) {
            $this->onFailure($serviceName, $state, $serviceConfig, $e);
            throw $e;
        }
    }

    private function onSuccess(string $serviceName, array $state, array $config): void
    {
        if ($state['state'] === 'half_open') {
            $newCount = ($state['success_count'] ?? 0) + 1;
            if ($newCount >= $config['success_threshold']) {
                $this->transitionTo($serviceName, 'closed');
                $this->logger->info('Circuit breaker closed after recovery', [
                    'circuit_service' => $serviceName,
                ]);
            }
        } elseif ($state['state'] === 'closed' && $state['failure_count'] > 0) {
            $this->resetFailures($serviceName);
        }
    }

    private function onFailure(string $serviceName, array $state, array $config, \Exception $e): void
    {
        $this->logger->error('Circuit breaker recorded failure', [
            'circuit_service' => $serviceName,
            'state' => $state['state'],
            'failure_count' => $state['failure_count'] + 1,
            'error' => $e->getMessage(),
        ]);

        if ($state['state'] === 'half_open') {
            $this->transitionTo($serviceName, 'open');
            return;
        }

        $newCount = $state['failure_count'] + 1;
        $this->db->execute(
            'INSERT INTO circuit_breaker_state (service_name, state, failure_count, last_failure_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE failure_count = ?, last_failure_at = NOW()',
            [$serviceName, 'closed', $newCount, $newCount]
        );

        if ($newCount >= $config['failure_threshold']) {
            $this->transitionTo($serviceName, 'open');
            $this->logger->critical('Circuit breaker opened', [
                'circuit_service' => $serviceName,
                'failure_count' => $newCount,
                'threshold' => $config['failure_threshold'],
            ]);
        }
    }

    private function transitionTo(string $serviceName, string $newState): void
    {
        $columns = ['state' => $newState, 'updated_at' => date('Y-m-d H:i:s')];

        if ($newState === 'open') {
            $columns['opened_at'] = date('Y-m-d H:i:s');
        } elseif ($newState === 'half_open') {
            $columns['half_open_at'] = date('Y-m-d H:i:s');
        } elseif ($newState === 'closed') {
            $columns['failure_count'] = 0;
            $columns['opened_at'] = null;
            $columns['half_open_at'] = null;
        }

        $this->db->execute(
            'INSERT INTO circuit_breaker_state (service_name, state, failure_count, opened_at, half_open_at)
             VALUES (?, ?, 0, NULL, NULL)
             ON DUPLICATE KEY UPDATE state = ?, failure_count = ?, opened_at = ?, half_open_at = ?',
            [
                $serviceName,
                $newState,
                $newState,
                $columns['failure_count'] ?? 0,
                $columns['opened_at'] ?? null,
                $columns['half_open_at'] ?? null,
            ]
        );
    }

    private function getState(string $serviceName): array
    {
        $stmt = $this->db->execute(
            'SELECT * FROM circuit_breaker_state WHERE service_name = ?',
            [$serviceName]
        );
        $state = $stmt->fetch();

        if (!$state) {
            return [
                'service_name' => $serviceName,
                'state' => 'closed',
                'failure_count' => 0,
                'last_failure_at' => null,
                'opened_at' => null,
                'half_open_at' => null,
            ];
        }

        return $state;
    }

    private function resetFailures(string $serviceName): void
    {
        $this->db->execute(
            'UPDATE circuit_breaker_state SET failure_count = 0 WHERE service_name = ?',
            [$serviceName]
        );
    }

    private function getServiceConfig(string $serviceName): array
    {
        return $this->config[$serviceName] ?? [
            'failure_threshold' => 5,
            'recovery_timeout' => 30,
            'success_threshold' => 3,
            'timeout' => 10,
        ];
    }
}
