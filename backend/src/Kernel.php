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
use App\Controller\RoleController;
use App\Controller\UserController;
use App\Controller\DepartmentController;
use App\Controller\BudgetController;
use App\Controller\RequisitionController;
use App\Controller\PurchaseOrderController;
use App\Controller\GoodsReceivedNoteController;
use App\Controller\MatchController;
use App\Controller\ComplianceController;
use App\Controller\VendorStagingController;
use App\Controller\VendorRequestController;
use App\Controller\ReportController;
use App\Controller\FileImportController;
use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
use App\Repository\UserRoleRepository;
use App\Repository\VendorDocumentRepository;
use App\Repository\DepartmentRepository;
use App\Repository\BudgetRepository;
use App\Repository\RequisitionRepository;
use App\Repository\PurchaseOrderRepository;
use App\Repository\GoodsReceivedNoteRepository;
use App\Repository\MatchResultRepository;
use App\Repository\ComplianceSettingsRepository;
use App\Repository\ComplianceAlertRepository;
use App\Repository\VendorStagingRepository;
use App\Repository\VendorRequestRepository;
use App\Repository\FileImportRepository;
use App\Repository\EmailNotificationRepository;
use App\Service\RbacService;
use App\Service\VendorLifecycleService;
use App\Service\VendorDocumentService;
use App\Service\DepartmentService;
use App\Service\BudgetService;
use App\Service\RequisitionService;
use App\Service\PurchaseOrderService;
use App\Service\GoodsReceivedNoteService;
use App\Service\ThreeWayMatchService;
use App\Service\ComplianceService;
use App\Service\VendorStagingService;
use App\Service\CsvImportService;
use App\Service\VendorRequestService;
use App\Service\ReportService;
use App\Service\ExportService;
use App\Service\FileImportService;
use App\Service\EmailNotificationService;
use App\Middleware\RequestIdMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\ErrorHandlerMiddleware;
use App\Middleware\PermissionMiddleware;

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
                    $c->get(PdfService::class),
                    $c->get(VendorService::class),
                    $c->get(InvoiceExtractionService::class)
                );
            },
            VSphereController::class => function (Container $c) {
                return new VSphereController($c->get(VSphereService::class));
            },

            // RBAC Repositories
            RoleRepository::class => function (Container $c) {
                return new RoleRepository($c->get(Connection::class));
            },
            PermissionRepository::class => function (Container $c) {
                return new PermissionRepository($c->get(Connection::class));
            },
            UserRoleRepository::class => function (Container $c) {
                return new UserRoleRepository($c->get(Connection::class));
            },

            // New Repositories
            VendorDocumentRepository::class => function (Container $c) {
                return new VendorDocumentRepository($c->get(Connection::class));
            },
            DepartmentRepository::class => function (Container $c) {
                return new DepartmentRepository($c->get(Connection::class));
            },
            BudgetRepository::class => function (Container $c) {
                return new BudgetRepository($c->get(Connection::class));
            },
            RequisitionRepository::class => function (Container $c) {
                return new RequisitionRepository($c->get(Connection::class));
            },
            PurchaseOrderRepository::class => function (Container $c) {
                return new PurchaseOrderRepository($c->get(Connection::class));
            },
            GoodsReceivedNoteRepository::class => function (Container $c) {
                return new GoodsReceivedNoteRepository($c->get(Connection::class));
            },
            MatchResultRepository::class => function (Container $c) {
                return new MatchResultRepository($c->get(Connection::class));
            },
            ComplianceSettingsRepository::class => function (Container $c) {
                return new ComplianceSettingsRepository($c->get(Connection::class));
            },
            ComplianceAlertRepository::class => function (Container $c) {
                return new ComplianceAlertRepository($c->get(Connection::class));
            },
            VendorStagingRepository::class => function (Container $c) {
                return new VendorStagingRepository($c->get(Connection::class));
            },
            VendorRequestRepository::class => function (Container $c) {
                return new VendorRequestRepository($c->get(Connection::class));
            },
            FileImportRepository::class => function (Container $c) {
                return new FileImportRepository($c->get(Connection::class));
            },
            EmailNotificationRepository::class => function (Container $c) {
                return new EmailNotificationRepository($c->get(Connection::class));
            },

            // RBAC Service
            RbacService::class => function (Container $c) {
                return new RbacService(
                    $c->get(RoleRepository::class),
                    $c->get(PermissionRepository::class),
                    $c->get(UserRoleRepository::class),
                    $c->get(StructuredLogger::class)
                );
            },

            // New Services
            VendorLifecycleService::class => function (Container $c) {
                return new VendorLifecycleService(
                    $c->get(VendorRepository::class),
                    $c->get(AuditLogRepository::class),
                    $c->get(StructuredLogger::class)
                );
            },
            VendorDocumentService::class => function (Container $c) use ($appConfig) {
                return new VendorDocumentService(
                    $c->get(VendorDocumentRepository::class),
                    $c->get(StructuredLogger::class),
                    $appConfig['upload_dir'] . '/vendor-documents'
                );
            },
            DepartmentService::class => function (Container $c) {
                return new DepartmentService(
                    $c->get(DepartmentRepository::class),
                    $c->get(StructuredLogger::class)
                );
            },
            BudgetService::class => function (Container $c) {
                return new BudgetService(
                    $c->get(BudgetRepository::class),
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class)
                );
            },
            RequisitionService::class => function (Container $c) {
                return new RequisitionService(
                    $c->get(RequisitionRepository::class),
                    $c->get(BudgetService::class),
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class)
                );
            },
            PurchaseOrderService::class => function (Container $c) {
                return new PurchaseOrderService(
                    $c->get(PurchaseOrderRepository::class),
                    $c->get(RequisitionRepository::class),
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class)
                );
            },
            GoodsReceivedNoteService::class => function (Container $c) {
                return new GoodsReceivedNoteService(
                    $c->get(GoodsReceivedNoteRepository::class),
                    $c->get(PurchaseOrderRepository::class),
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class)
                );
            },
            ThreeWayMatchService::class => function (Container $c) {
                return new ThreeWayMatchService(
                    $c->get(MatchResultRepository::class),
                    $c->get(PurchaseOrderRepository::class),
                    $c->get(GoodsReceivedNoteRepository::class),
                    $c->get(InvoiceRepository::class),
                    $c->get(ComplianceSettingsRepository::class),
                    $c->get(StructuredLogger::class)
                );
            },
            ComplianceService::class => function (Container $c) {
                return new ComplianceService(
                    $c->get(ComplianceSettingsRepository::class),
                    $c->get(ComplianceAlertRepository::class),
                    $c->get(VendorRepository::class),
                    $c->get(VendorDocumentRepository::class),
                    $c->get(BudgetRepository::class),
                    $c->get(StructuredLogger::class)
                );
            },
            VendorStagingService::class => function (Container $c) {
                return new VendorStagingService(
                    $c->get(VendorStagingRepository::class),
                    $c->get(VendorRepository::class),
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class)
                );
            },
            CsvImportService::class => function (Container $c) {
                return new CsvImportService($c->get(StructuredLogger::class));
            },
            VendorRequestService::class => function (Container $c) {
                return new VendorRequestService(
                    $c->get(VendorRequestRepository::class),
                    $c->get(VendorRepository::class),
                    $c->get(Connection::class),
                    $c->get(StructuredLogger::class)
                );
            },
            ReportService::class => function (Container $c) {
                return new ReportService($c->get(Connection::class), $c->get(StructuredLogger::class));
            },
            ExportService::class => function (Container $c) {
                return new ExportService($c->get(StructuredLogger::class));
            },
            FileImportService::class => function (Container $c) {
                return new FileImportService(
                    $c->get(FileImportRepository::class),
                    $c->get(CsvImportService::class),
                    $c->get(VendorStagingService::class),
                    $c->get(StructuredLogger::class)
                );
            },
            EmailNotificationService::class => function (Container $c) {
                return new EmailNotificationService(
                    $c->get(EmailNotificationRepository::class),
                    $c->get(StructuredLogger::class)
                );
            },

            // New Controllers
            RoleController::class => function (Container $c) {
                return new RoleController($c->get(RbacService::class));
            },
            UserController::class => function (Container $c) {
                return new UserController($c->get(RbacService::class), $c->get(UserRoleRepository::class));
            },
            DepartmentController::class => function (Container $c) {
                return new DepartmentController($c->get(DepartmentService::class));
            },
            BudgetController::class => function (Container $c) {
                return new BudgetController($c->get(BudgetService::class));
            },
            RequisitionController::class => function (Container $c) {
                return new RequisitionController($c->get(RequisitionService::class));
            },
            PurchaseOrderController::class => function (Container $c) {
                return new PurchaseOrderController($c->get(PurchaseOrderService::class));
            },
            GoodsReceivedNoteController::class => function (Container $c) {
                return new GoodsReceivedNoteController($c->get(GoodsReceivedNoteService::class));
            },
            MatchController::class => function (Container $c) {
                return new MatchController($c->get(ThreeWayMatchService::class));
            },
            ComplianceController::class => function (Container $c) {
                return new ComplianceController($c->get(ComplianceService::class));
            },
            VendorStagingController::class => function (Container $c) {
                return new VendorStagingController(
                    $c->get(VendorStagingService::class),
                    $c->get(CsvImportService::class),
                    $c->get(FileImportService::class)
                );
            },
            VendorRequestController::class => function (Container $c) {
                return new VendorRequestController($c->get(VendorRequestService::class));
            },
            ReportController::class => function (Container $c) {
                return new ReportController($c->get(ReportService::class), $c->get(ExportService::class));
            },
            FileImportController::class => function (Container $c) {
                return new FileImportController($c->get(FileImportService::class));
            },
        ]);

        $container = $containerBuilder->build();
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        // Wire RBAC into AuthService for JWT permissions
        $container->get(AuthService::class)->setRbacService($container->get(RbacService::class));

        // Wire lifecycle/document services into VendorController
        $container->get(VendorController::class)->setLifecycleService($container->get(VendorLifecycleService::class));
        $container->get(VendorController::class)->setDocumentService($container->get(VendorDocumentService::class));

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
        $app->post('/api/vendors', [VendorController::class, 'create']);

        // Emails
        $app->post('/api/emails/ingest', [EmailController::class, 'ingest']);
        $app->get('/api/emails', [EmailController::class, 'list']);
        $app->get('/api/emails/{id}', [EmailController::class, 'get']);

        // Invoices
        $app->get('/api/invoices', [InvoiceController::class, 'list']);
        $app->get('/api/invoices/{id}', [InvoiceController::class, 'get']);
        $app->patch('/api/invoices/{id}', [InvoiceController::class, 'update']);
        $app->get('/api/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf']);
        $app->post('/api/invoices/upload', [InvoiceController::class, 'upload']);

        // vSphere
        $app->get('/api/vsphere/vms', [VSphereController::class, 'listVMs']);
        $app->get('/api/vsphere/datastores', [VSphereController::class, 'listDatastores']);
        $app->post('/api/vsphere/vms/{id}/power', [VSphereController::class, 'vmPowerAction']);

        // RBAC - Roles
        $app->get('/api/roles', [RoleController::class, 'list']);
        $app->post('/api/roles', [RoleController::class, 'create']);
        $app->get('/api/roles/{id}', [RoleController::class, 'get']);
        $app->put('/api/roles/{id}', [RoleController::class, 'update']);
        $app->delete('/api/roles/{id}', [RoleController::class, 'delete']);
        $app->get('/api/roles/{id}/permissions', [RoleController::class, 'getPermissions']);
        $app->post('/api/roles/{id}/permissions', [RoleController::class, 'setPermissions']);
        $app->get('/api/permissions', [RoleController::class, 'listAllPermissions']);

        // RBAC - Users
        $app->get('/api/users', [UserController::class, 'list']);
        $app->get('/api/users/{id}', [UserController::class, 'get']);
        $app->put('/api/users/{id}', [UserController::class, 'update']);
        $app->post('/api/users/{id}/roles', [UserController::class, 'assignRole']);
        $app->delete('/api/users/{id}/roles/{role_id}', [UserController::class, 'removeRole']);

        // Departments
        $app->get('/api/departments', [DepartmentController::class, 'list']);
        $app->post('/api/departments', [DepartmentController::class, 'create']);
        $app->get('/api/departments/{id}', [DepartmentController::class, 'get']);
        $app->put('/api/departments/{id}', [DepartmentController::class, 'update']);
        $app->delete('/api/departments/{id}', [DepartmentController::class, 'delete']);

        // Budgets
        $app->get('/api/budgets', [BudgetController::class, 'list']);
        $app->post('/api/budgets', [BudgetController::class, 'create']);
        $app->get('/api/budgets/{id}', [BudgetController::class, 'get']);
        $app->put('/api/budgets/{id}', [BudgetController::class, 'update']);
        $app->get('/api/budgets/{id}/utilization', [BudgetController::class, 'utilization']);

        // Requisitions
        $app->get('/api/requisitions', [RequisitionController::class, 'list']);
        $app->post('/api/requisitions', [RequisitionController::class, 'create']);
        $app->get('/api/requisitions/{id}', [RequisitionController::class, 'get']);
        $app->post('/api/requisitions/{id}/submit', [RequisitionController::class, 'submit']);
        $app->post('/api/requisitions/{id}/approve', [RequisitionController::class, 'approve']);
        $app->post('/api/requisitions/{id}/reject', [RequisitionController::class, 'reject']);

        // Purchase Orders
        $app->get('/api/purchase-orders', [PurchaseOrderController::class, 'list']);
        $app->post('/api/purchase-orders', [PurchaseOrderController::class, 'create']);
        $app->get('/api/purchase-orders/{id}', [PurchaseOrderController::class, 'get']);
        $app->put('/api/purchase-orders/{id}', [PurchaseOrderController::class, 'update']);

        // Goods Received Notes
        $app->get('/api/grns', [GoodsReceivedNoteController::class, 'list']);
        $app->post('/api/grns', [GoodsReceivedNoteController::class, 'create']);
        $app->get('/api/grns/{id}', [GoodsReceivedNoteController::class, 'get']);

        // 3-Way Matching
        $app->get('/api/matching', [MatchController::class, 'list']);
        $app->get('/api/matching/{id}', [MatchController::class, 'get']);
        $app->post('/api/matching/run/{invoice_id}', [MatchController::class, 'runMatch']);

        // Compliance
        $app->get('/api/compliance/alerts', [ComplianceController::class, 'listAlerts']);
        $app->get('/api/compliance/settings', [ComplianceController::class, 'getSettings']);
        $app->put('/api/compliance/settings', [ComplianceController::class, 'updateSettings']);
        $app->post('/api/compliance/alerts/{id}/resolve', [ComplianceController::class, 'resolveAlert']);

        // Vendor Documents
        $app->get('/api/vendors/{id}/documents', [VendorController::class, 'listDocuments']);
        $app->post('/api/vendors/{id}/documents', [VendorController::class, 'uploadDocument']);
        $app->post('/api/vendors/{id}/verify', [VendorController::class, 'verify']);
        $app->post('/api/vendors/{id}/block', [VendorController::class, 'block']);

        // Vendor Staging / Migration
        $app->get('/api/staging', [VendorStagingController::class, 'list']);
        $app->post('/api/staging/import', [VendorStagingController::class, 'importCsv']);
        $app->get('/api/staging/{id}', [VendorStagingController::class, 'get']);
        $app->post('/api/staging/{id}/promote', [VendorStagingController::class, 'promote']);

        // Vendor Requests
        $app->get('/api/vendor-requests', [VendorRequestController::class, 'list']);
        $app->post('/api/vendor-requests', [VendorRequestController::class, 'create']);
        $app->get('/api/vendor-requests/{id}', [VendorRequestController::class, 'get']);
        $app->put('/api/vendor-requests/{id}', [VendorRequestController::class, 'update']);
        $app->post('/api/vendor-requests/{id}/submit', [VendorRequestController::class, 'submit']);
        $app->post('/api/vendor-requests/{id}/review', [VendorRequestController::class, 'review']);
        $app->post('/api/vendor-requests/{id}/promote', [VendorRequestController::class, 'promote']);

        // Reports
        $app->get('/api/reports/available', [ReportController::class, 'available']);
        $app->get('/api/reports/{type}', [ReportController::class, 'getReport']);
        $app->get('/api/reports/{type}/export', [ReportController::class, 'exportReport']);

        // File Imports
        $app->get('/api/imports', [FileImportController::class, 'list']);
        $app->post('/api/imports/upload', [FileImportController::class, 'upload']);
        $app->get('/api/imports/{id}', [FileImportController::class, 'get']);
        $app->post('/api/imports/{id}/retry', [FileImportController::class, 'retry']);
    }
}
