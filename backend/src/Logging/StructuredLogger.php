<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\Processor\ProcessorInterface;
use Monolog\LogRecord;

class StructuredLogger
{
    private Logger $logger;
    private string $requestId;

    public function __construct(string $channel = 'backend', ?string $requestId = null)
    {
        $this->requestId = $requestId ?? $this->generateRequestId();
        $this->logger = new Logger($channel);

        $handler = new StreamHandler('php://stdout', Level::Debug);
        $formatter = new JsonFormatter();
        $formatter->includeStacktraces(true);
        $handler->setFormatter($formatter);

        $this->logger->pushHandler($handler);
        $this->logger->pushProcessor(new RequestIdProcessor($this->requestId));
        $this->logger->pushProcessor(new SensitiveDataRedactor());
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $this->enrichContext($context));
    }

    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $this->enrichContext($context));
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $this->enrichContext($context));
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $this->enrichContext($context));
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $this->enrichContext($context));
    }

    public function notice(string $message, array $context = []): void
    {
        $this->logger->notice($message, $this->enrichContext($context));
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $this->enrichContext($context));
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $this->enrichContext($context));
    }

    private function enrichContext(array $context): array
    {
        $context['service'] = $this->logger->getName();
        $context['timestamp'] = gmdate('Y-m-d\TH:i:s.u\Z');
        return $context;
    }

    private function generateRequestId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

class RequestIdProcessor implements ProcessorInterface
{
    private string $requestId;

    public function __construct(string $requestId)
    {
        $this->requestId = $requestId;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: array_merge($record->extra, [
            'request_id' => $this->requestId,
        ]));
    }
}

class SensitiveDataRedactor implements ProcessorInterface
{
    private const SENSITIVE_KEYS = [
        'password', 'token', 'authorization', 'secret', 'api_key',
        'credit_card', 'ssn', 'session_id', 'cookie', 'csrf',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->redact($record->context);
        return $record->with(context: $context);
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = '[REDACTED]';
            }
        }
        return $data;
    }

    private function isSensitive(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }
        return false;
    }
}
