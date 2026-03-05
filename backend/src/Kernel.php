<?php

declare(strict_types=1);

namespace App;

use DI\Container;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\App;
use App\Database\Connection;
use App\Logging\StructuredLogger;
use App\Resilience\CircuitBreaker;
use App\Resilience\RateLimiter;
use App\Security\ChecksumService;
use App\Service\AuthService;
use App\Service\VendorService;
use App\Service\PdfService;
use App\Service\InvoiceExtractionService;
use App\Service\EmailIngestionService;
use App\Service\VSphereService;
use App\Repository\VendorRepository;
use App\Repository\EmailRepository;
use App\Repository\InvoiceRepository;
use App\Repository\AuditLogRepository;
use App\Controller\HealthController;
use App\Controller\AuthController;
use App\Controller\VendorController;
use App\Controller\EmailController;
use App\Controller\InvoiceController;
use App\Controller\VSphereController;
use App\Middleware\RequestIdMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\ErrorHandlerMiddleware;

class Kernel
{
    public static function bootstrap(): App
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $dbConfig = require __DIR__ . '/../config/database.php';
        $rateLimitConfig = require __DIR__ . '/../config/rate_limiting.php';
        $circuitBreakerConfig = require __DIR__ . '/../config/circuit_breaker.php';
        $vsphereConfig = require __DIR__ . '/../config/vsphere.php';

        $containerBuilder = new ContainerBuilder();

        $containerBuilder->addDefinitions([
            // Core services
            StructuredLogger::class => function () {
                return new StructuredLogger('backend');
            },

            Connection::class => function (Container $c) use ($dbConfig) {
                return new Connection(
                    $dbConfig['dsn'],
                    $dbConfig['user'],
                    $dbConfig['pass'],
                    $c->get(StructuredLogger::class)
                );
            },

            // Security
            ChecksumService::class => function (Container $c) {
                return new ChecksumService($c->get(StructuredLogger::class));
            },

            // Resilience
            CircuitBreaker::class => function (Container $c) use ($circuitBreakerConfig) {
                return new CircuitBreaker(
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class),
                    $circuitBreakerConfig
                );
            },

            RateLimiter::class => function (Container $c) use ($rateLimitConfig) {
                return new RateLimiter(
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class),
                    $rateLimitConfig
                );
            },

            // Repositories
            VendorRepository::class => function (Container $c) {
                return new VendorRepository($c->get(Connection::class));
            },
            EmailRepository::class => function (Container $c) {
                return new EmailRepository($c->get(Connection::class));
            },
            InvoiceRepository::class => function (Container $c) {
                return new InvoiceRepository($c->get(Connection::class));
            },
            AuditLogRepository::class => function (Container $c) {
                return new AuditLogRepository($c->get(Connection::class));
            },

            // Services
            AuthService::class => function (Container $c) use ($appConfig) {
                return new AuthService(
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class),
                    $appConfig['jwt_secret'],
                    $appConfig['jwt_expiry']
                );
            },

            VendorService::class => function (Container $c) {
                return new VendorService(
                    $c->get(VendorRepository::class),
                    $c->get(StructuredLogger::class)
                );
            },

            PdfService::class => function (Container $c) use ($appConfig) {
                return new PdfService(
                    $appConfig['upload_dir'],
                    $c->get(ChecksumService::class),
                    $c->get(StructuredLogger::class)
                );
            },

            InvoiceExtractionService::class => function (Container $c) use ($appConfig) {
                return new InvoiceExtractionService(
                    $c->get(StructuredLogger::class),
                    $appConfig['tesseract_lang']
                );
            },

            EmailIngestionService::class => function (Container $c) {
                return new EmailIngestionService(
                    $c->get(EmailRepository::class),
                    $c->get(InvoiceRepository::class),
                    $c->get(AuditLogRepository::class),
                    $c->get(VendorService::class),
                    $c->get(PdfService::class),
                    $c->get(InvoiceExtractionService::class),
                    $c->get(StructuredLogger::class)
                );
            },

            VSphereService::class => function (Container $c) use ($vsphereConfig) {
                return new VSphereService(
                    $c->get(CircuitBreaker::class),
                    $c->get(StructuredLogger::class),
                    $vsphereConfig['base_url'],
                    $vsphereConfig['user'],
                    $vsphereConfig['pass'],
                    $vsphereConfig['ssl_verify']
                );
            },

            // Controllers
            HealthController::class => function (Container $c) use ($appConfig) {
                return new HealthController(
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class),
                    $appConfig['upload_dir']
                );
            },
            AuthController::class => function (Container $c) {
                return new AuthController($c->get(AuthService::class));
            },
            VendorController::class => function (Container $c) {
                return new VendorController(
                    $c->get(VendorService::class),
                    $c->get(EmailRepository::class),
                    $c->get(InvoiceRepository::class)
                );
            },
            EmailController::class => function (Container $c) {
                return new EmailController(
                    $c->get(EmailIngestionService::class),
                    $c->get(EmailRepository::class)
                );
            },
            InvoiceController::class => function (Container $c) use ($appConfig) {
                return new InvoiceController(
                    $c->get(InvoiceRepository::class),
                    $c->get(PdfService::class)
                );
            },
            VSphereController::class => function (Container $c) {
                return new VSphereController($c->get(VSphereService::class));
            },
        ]);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        // Middleware stack (executed bottom to top)
        $app->addBodyParsingMiddleware();

        $logger = $container->get(StructuredLogger::class);

        $app->add(new RateLimitMiddleware($container->get(RateLimiter::class)));
        $app->add(new AuthMiddleware(
            $container->get(AuthService::class),
            $logger,
            $appConfig['public_routes']
        ));
        $app->add(new CorsMiddleware($appConfig['allowed_origins']));
        $app->add(new RequestIdMiddleware($logger));
        $app->add(new ErrorHandlerMiddleware($logger));

        // Routes
        self::registerRoutes($app);

        return $app;
    }

    private static function registerRoutes(App $app): void
    {
        // Health
        $app->get('/api/health', [HealthController::class, 'health']);
        $app->get('/api/health/ready', [HealthController::class, 'ready']);

        // Auth
        $app->post('/api/auth/login', [AuthController::class, 'login']);
        $app->post('/api/auth/refresh', [AuthController::class, 'refresh']);

        // Vendors
        $app->get('/api/vendors', [VendorController::class, 'list']);
        $app->get('/api/vendors/{id}', [VendorController::class, 'get']);
        $app->get('/api/vendors/{id}/emails', [VendorController::class, 'emails']);
        $app->get('/api/vendors/{id}/invoices', [VendorController::class, 'invoices']);

        // Emails
        $app->post('/api/emails/ingest', [EmailController::class, 'ingest']);
        $app->get('/api/emails', [EmailController::class, 'list']);
        $app->get('/api/emails/{id}', [EmailController::class, 'get']);

        // Invoices
        $app->get('/api/invoices', [InvoiceController::class, 'list']);
        $app->get('/api/invoices/{id}', [InvoiceController::class, 'get']);
        $app->patch('/api/invoices/{id}', [InvoiceController::class, 'update']);
        $app->get('/api/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf']);

        // vSphere
        $app->get('/api/vsphere/vms', [VSphereController::class, 'listVMs']);
        $app->get('/api/vsphere/datastores', [VSphereController::class, 'listDatastores']);
        $app->post('/api/vsphere/vms/{id}/power', [VSphereController::class, 'vmPowerAction']);
    }
}
