<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Structured JSON logger compatible with Splunk ingestion.
 *
 * Output format per line:
 * {"timestamp":"...","level":"INFO","service":"frontend","request_id":"...","user_id":"...","message":"...","context":{...}}
 */
class StructuredLogger implements LoggerInterface
{
    use LoggerTrait;

    private Logger $logger;

    public function __construct(
        string $channel = 'frontend',
        string $logPath = '/var/www/html/var/log/app.log',
        string $level = 'info',
    ) {
        $this->logger = new Logger($channel);

        $monoLevel = Level::fromName($level);
        $handler = new StreamHandler($logPath, $monoLevel);

        $formatter = new JsonFormatter();
        $formatter->includeStacktraces(true);
        $handler->setFormatter($formatter);

        $this->logger->pushHandler($handler);

        // Add structured processor for Splunk-friendly fields
        $this->logger->pushProcessor(new class implements ProcessorInterface {
            public function __invoke(LogRecord $record): LogRecord
            {
                $extra = $record->extra;
                $extra['service'] = 'readinvoice-frontend';
                $extra['request_id'] = $_SERVER['HTTP_X_REQUEST_ID']
                    ?? $_SERVER['REQUEST_ID']
                    ?? substr(bin2hex(random_bytes(8)), 0, 16);
                $extra['user_id'] = $_SESSION['user']['id'] ?? null;
                $extra['timestamp_unix'] = microtime(true);
                $extra['hostname'] = gethostname() ?: 'unknown';

                return $record->with(extra: $extra);
            }
        });

        // Also log to stderr in development
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $stderrHandler = new StreamHandler('php://stderr', Level::Debug);
            $stderrHandler->setFormatter($formatter);
            $this->logger->pushHandler($stderrHandler);
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, (string) $message, $context);
    }
}
