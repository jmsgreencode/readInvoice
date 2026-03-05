<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use App\Middleware\ErrorHandlerMiddleware;
use App\Exception\AppException;
use App\Exception\ValidationException;
use App\Logging\StructuredLogger;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Mockery;

class ErrorHandlerMiddlewareTest extends TestCase
{
    private ErrorHandlerMiddleware $middleware;

    protected function setUp(): void
    {
        $logger = Mockery::mock(StructuredLogger::class)->shouldIgnoreMissing();
        $logger->shouldReceive('getRequestId')->andReturn('test-request-id');
        $this->middleware = new ErrorHandlerMiddleware($logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testPassesThroughSuccessfulResponse(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->andReturn(new Response(200));

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandlesAppException(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/test');
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->andThrow(
            new AppException('Something went wrong', 'TEST_ERROR', 400, 'A safe message')
        );

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertEquals('TEST_ERROR', $body['error']['code']);
        $this->assertEquals('A safe message', $body['error']['message']);
    }

    public function testAdminSeesDetailedError(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/test');
        $request = $request->withAttribute('user_role', 'admin');

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->andThrow(
            new AppException('Detailed technical error', 'TEST_ERROR', 500, 'Safe message')
        );

        $response = $this->middleware->process($request, $handler);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('detail', $body['error']);
        $this->assertEquals('Detailed technical error', $body['error']['detail']);
    }

    public function testUserDoesNotSeeDetailedError(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/test');
        $request = $request->withAttribute('user_role', 'user');

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->andThrow(
            new AppException('Detailed technical error', 'TEST_ERROR', 500, 'Safe message')
        );

        $response = $this->middleware->process($request, $handler);

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayNotHasKey('detail', $body['error']);
        $this->assertEquals('Safe message', $body['error']['message']);
    }

    public function testHandlesValidationException(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/test');

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->andThrow(
            new ValidationException(['email' => 'Invalid email'])
        );

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('VALIDATION_ERROR', $body['error']['code']);
        $this->assertArrayHasKey('validation_errors', $body['error']);
    }

    public function testHandlesUnexpectedException(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/test');

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->andThrow(new \RuntimeException('Unexpected'));

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(500, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('INTERNAL_ERROR', $body['error']['code']);
        $this->assertStringNotContainsString('Unexpected', $body['error']['message']);
    }
}
