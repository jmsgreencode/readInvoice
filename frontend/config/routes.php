<?php

declare(strict_types=1);

use ReadInvoice\Frontend\Controller\AuthPageController;
use ReadInvoice\Frontend\Controller\DashboardController;
use ReadInvoice\Frontend\Controller\InvoiceViewController;
use ReadInvoice\Frontend\Controller\VendorEmailController;
use ReadInvoice\Frontend\Middleware\AuthMiddleware;
use ReadInvoice\Frontend\Middleware\RateLimitMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Health check (no auth required)
    $app->get('/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok', 'service' => 'frontend']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Auth routes (no auth middleware)
    $app->get('/login', [AuthPageController::class, 'showLogin']);
    $app->post('/login', [AuthPageController::class, 'handleLogin'])
        ->add(RateLimitMiddleware::class);
    $app->get('/logout', [AuthPageController::class, 'logout']);

    // Protected routes
    $app->group('', function (RouteCollectorProxy $group) {
        // Dashboard
        $group->get('/', [DashboardController::class, 'index']);
        $group->get('/dashboard', [DashboardController::class, 'index']);

        // Vendor SSE endpoints
        $group->get('/vendors', [VendorEmailController::class, 'vendorListFragment']);
        $group->get('/vendors/{id}/emails', [VendorEmailController::class, 'vendorEmailsFragment']);

        // Invoice SSE endpoints
        $group->get('/invoices/{id}', [InvoiceViewController::class, 'invoiceDetailFragment']);
        $group->get('/invoices/{id}/download', [InvoiceViewController::class, 'downloadPdf']);
    })->add(AuthMiddleware::class);
};
