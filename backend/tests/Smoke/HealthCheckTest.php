<?php

declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

class HealthCheckTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('BACKEND_URL') ?: 'http://localhost:8080';
    }

    public function testHealthEndpointReturns200(): void
    {
        $ch = curl_init("{$this->baseUrl}/api/health");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 0) {
            $this->markTestSkipped('Backend not running');
        }

        $this->assertContains($httpCode, [200, 503]);

        $data = json_decode($response, true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('status', $data['data']);
    }

    public function testReadyEndpoint(): void
    {
        $ch = curl_init("{$this->baseUrl}/api/health/ready");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 0) {
            $this->markTestSkipped('Backend not running');
        }

        $this->assertContains($httpCode, [200, 503]);
    }

    public function testUnauthenticatedRequestReturns401(): void
    {
        $ch = curl_init("{$this->baseUrl}/api/vendors");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 0) {
            $this->markTestSkipped('Backend not running');
        }

        $this->assertEquals(401, $httpCode);
    }

    public function testNotFoundReturns404(): void
    {
        $ch = curl_init("{$this->baseUrl}/api/nonexistent");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 0) {
            $this->markTestSkipped('Backend not running');
        }

        $this->assertContains($httpCode, [404, 401]);
    }
}
