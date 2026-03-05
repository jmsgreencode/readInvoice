<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend;

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use GuzzleHttp\Client as GuzzleClient;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReadInvoice\Frontend\Logging\StructuredLogger;
use ReadInvoice\Frontend\Middleware\CircuitBreakerMiddleware;
use ReadInvoice\Frontend\Middleware\ErrorHandlerMiddleware;
use ReadInvoice\Frontend\Middleware\RateLimitMiddleware;
use ReadInvoice\Frontend\Service\BackendApiClient;
use ReadInvoice\Frontend\Service\DatastarResponseBuilder;
use ReadInvoice\Frontend\Service\SessionService;
use Slim\App;

class Kernel
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/app.php';
    }

    public function bootstrap(): App
    {
        $containerBuilder = new ContainerBuilder();

        $config = $this->config;

        $containerBuilder->addDefinitions([
            'config' => $config,

            LoggerInterface::class => function () use ($config): LoggerInterface {
                return new StructuredLogger(
                    $config['logging']['channel'],
                    $config['logging']['path'],
                    $config['logging']['level']
                );
            },

            SessionService::class => function (): SessionService {
                return new SessionService();
            },

            GuzzleClient::class => function () use ($config): GuzzleClient {
                return new GuzzleClient([
                    'base_uri' => $config['backend']['url'],
                    'timeout' => $config['backend']['timeout'],
                    'connect_timeout' => $config['backend']['connect_timeout'],
                    'http_errors' => false,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                ]);
            },

            BackendApiClient::class => function (ContainerInterface $c): BackendApiClient {
                return new BackendApiClient(
                    $c->get(GuzzleClient::class),
                    $c->get(LoggerInterface::class),
                    $c->get(SessionService::class)
                );
            },

            DatastarResponseBuilder::class => function (): DatastarResponseBuilder {
                return new DatastarResponseBuilder();
            },

            CircuitBreakerMiddleware::class => function (ContainerInterface $c): CircuitBreakerMiddleware {
                $config = $c->get('config');
                return new CircuitBreakerMiddleware(
                    $c->get(LoggerInterface::class),
                    $config['circuit_breaker']['failure_threshold'],
                    $config['circuit_breaker']['recovery_timeout']
                );
            },

            RateLimitMiddleware::class => function (ContainerInterface $c): RateLimitMiddleware {
                $config = $c->get('config');
                return new RateLimitMiddleware(
                    $config['rate_limit']['max_requests'],
                    $config['rate_limit']['window_seconds']
                );
            },

            ErrorHandlerMiddleware::class => function (ContainerInterface $c): ErrorHandlerMiddleware {
                $config = $c->get('config');
                return new ErrorHandlerMiddleware(
                    $c->get(LoggerInterface::class),
                    $c->get(SessionService::class),
                    $config['app']['debug']
                );
            },
        ]);

        $container = $containerBuilder->build();
        $app = Bridge::create($container);

        // Register middleware (outer to inner)
        $app->add(ErrorHandlerMiddleware::class);
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        // Register routes
        $routes = require __DIR__ . '/../config/routes.php';
        $routes($app);

        return $app;
    }
}
