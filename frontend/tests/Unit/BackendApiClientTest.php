<?php

declare(strict_types=1);

namespace ReadInvoice\Frontend\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReadInvoice\Frontend\Service\BackendApiClient;
use ReadInvoice\Frontend\Service\SessionService;

class BackendApiClientTest extends TestCase
{
    private function createClient(array $responses): BackendApiClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $session = $this->createMock(SessionService::class);
        $session->method('start')->willReturn(null);
        $session->method('get')->willReturn(null);

        return new BackendApiClient($guzzle, new NullLogger(), $session);
    }

    public function testGetReturnsDecodedJsonResponse(): void
    {
        $client = $this->createClient([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    ['id' => 1, 'name' => 'Vendor A'],
                    ['id' => 2, 'name' => 'Vendor B'],
                ],
            ])),
        ]);

        $result = $client->get('/api/vendors');

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
        $this->assertSame('Vendor A', $result['data'][0]['name']);
    }

    public function testPostSendsDataAndReturnsResponse(): void
    {
        $client = $this->createClient([
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode([
                'token' => 'jwt-token-123',
                'user' => ['id' => 1, 'email' => 'test@example.com'],
            ])),
        ]);

        $result = $client->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret',
        ]);

        $this->assertSame('jwt-token-123', $result['token']);
        $this->assertSame('test@example.com', $result['user']['email']);
    }

    public function testThrowsExceptionOn500Response(): void
    {
        $client = $this->createClient([
            new GuzzleResponse(500, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'Internal Server Error',
            ])),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Backend API error');

        $client->get('/api/vendors');
    }

    public function testThrowsExceptionOn401ClearsSession(): void
    {
        $session = $this->createMock(SessionService::class);
        $session->method('start')->willReturn(null);
        $session->method('get')->willReturn('some-token');
        $session->expects($this->once())->method('remove')->with('jwt_token');

        $mock = new MockHandler([
            new GuzzleResponse(401, ['Content-Type' => 'application/json'], json_encode([
                'message' => 'Token expired',
            ])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $client = new BackendApiClient($guzzle, new NullLogger(), $session);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(401);

        $client->get('/api/vendors');
    }

    public function testThrowsExceptionOnConnectionFailure(): void
    {
        $client = $this->createClient([
            new ConnectException(
                'Connection refused',
                new GuzzleRequest('GET', '/api/vendors')
            ),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Backend service is unavailable');

        $client->get('/api/vendors');
    }

    public function testGetRawReturnsBodyAndHeaders(): void
    {
        $pdfContent = '%PDF-1.4 fake pdf content';
        $client = $this->createClient([
            new GuzzleResponse(200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="invoice.pdf"',
            ], $pdfContent),
        ]);

        $result = $client->getRaw('/api/invoices/1/download');

        $this->assertSame($pdfContent, $result['body']);
        $this->assertSame('application/pdf', $result['content_type']);
        $this->assertStringContainsString('invoice.pdf', $result['content_disposition']);
    }

    public function testGetRawThrowsOn404(): void
    {
        $client = $this->createClient([
            new GuzzleResponse(404, [], 'Not Found'),
        ]);

        $this->expectException(\RuntimeException::class);

        $client->getRaw('/api/invoices/999/download');
    }
}
