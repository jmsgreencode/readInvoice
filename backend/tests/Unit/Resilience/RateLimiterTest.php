<?php

declare(strict_types=1);

namespace Tests\Unit\Resilience;

use PHPUnit\Framework\TestCase;
use App\Resilience\RateLimiter;
use App\Database\Connection;
use App\Logging\StructuredLogger;
use App\Exception\RateLimitExceededException;
use Mockery;
use PDOStatement;

class RateLimiterTest extends TestCase
{
    private $mockDb;
    private $mockLogger;

    protected function setUp(): void
    {
        $this->mockDb = Mockery::mock(Connection::class);
        $this->mockLogger = Mockery::mock(StructuredLogger::class)->shouldIgnoreMissing();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testAllowsRequestUnderLimit(): void
    {
        $config = ['default' => ['max_requests' => 10, 'window_seconds' => 60]];
        $limiter = new RateLimiter($this->mockDb, $this->mockLogger, $config);

        $mockStmt = Mockery::mock(PDOStatement::class);
        $mockStmt->shouldReceive('fetch')->andReturn(['cnt' => 5]);

        $this->mockDb->shouldReceive('execute')->andReturn($mockStmt);

        $result = $limiter->check('test-key');

        $this->assertTrue($result['allowed']);
        $this->assertEquals(10, $result['limit']);
    }

    public function testRejectsRequestOverLimit(): void
    {
        $config = ['default' => ['max_requests' => 5, 'window_seconds' => 60]];
        $limiter = new RateLimiter($this->mockDb, $this->mockLogger, $config);

        $mockStmt = Mockery::mock(PDOStatement::class);
        $mockStmt->shouldReceive('fetch')->andReturn(['cnt' => 5]);

        $this->mockDb->shouldReceive('execute')->andReturn($mockStmt);

        $this->expectException(RateLimitExceededException::class);

        $limiter->check('test-key');
    }

    public function testUsesRouteSpecificLimits(): void
    {
        $config = [
            'default' => ['max_requests' => 100, 'window_seconds' => 60],
            'routes' => [
                '/api/auth/login' => ['max_requests' => 5, 'window_seconds' => 300],
            ],
        ];
        $limiter = new RateLimiter($this->mockDb, $this->mockLogger, $config);

        $mockStmt = Mockery::mock(PDOStatement::class);
        $mockStmt->shouldReceive('fetch')->andReturn(['cnt' => 5]);

        $this->mockDb->shouldReceive('execute')->andReturn($mockStmt);

        $this->expectException(RateLimitExceededException::class);

        $limiter->check('test-key', '/api/auth/login');
    }
}
