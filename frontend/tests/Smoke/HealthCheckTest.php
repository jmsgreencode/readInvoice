<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Tests\Smoke;

use PHPUnit\Framework\TestCase;
use ReadInvoice\Frontend\Kernel;
use Slim\Psr7\Factory\ServerRequestFactory;

class HealthCheckTest extends TestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $kernel = new Kernel();
        $app = $kernel->bootstrap();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('ok', $body['status']);
        $this->assertSame('frontend', $body['service']);
    }

    public function testHealthEndpointReturnsJson(): void
    {
        $kernel = new Kernel();
        $app = $kernel->bootstrap();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');

        $response = $app->handle($request);

        $this->assertStringContainsString(
            'application/json',
            $response->getHeaderLine('Content-Type')
        );
    }
}
