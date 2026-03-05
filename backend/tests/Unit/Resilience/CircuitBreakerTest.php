<?php

declare(strict_types=1);

namespace Tests\Unit\Resilience;

use PHPUnit\Framework\TestCase;
use App\Resilience\CircuitBreaker;
use App\Database\Connection;
use App\Logging\StructuredLogger;
use App\Exception\CircuitOpenException;
use Mockery;
use PDOStatement;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;
    private $mockDb;
    private $mockLogger;

    protected function setUp(): void
    {
        $this->mockDb = Mockery::mock(Connection::class);
        $this->mockLogger = Mockery::mock(StructuredLogger::class)->shouldIgnoreMissing();

        $config = [
            'test_service' => [
                'failure_threshold' => 3,
                'recovery_timeout' => 10,
                'success_threshold' => 2,
                'timeout' => 5,
            ],
        ];

        $this->circuitBreaker = new CircuitBreaker($this->mockDb, $this->mockLogger, $config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testClosedCircuitAllowsCalls(): void
    {
        $mockStmt = Mockery::mock(PDOStatement::class);
        $mockStmt->shouldReceive('fetch')->andReturn(false);
        $this->mockDb->shouldReceive('execute')->andReturn($mockStmt);

        $result = $this->circuitBreaker->call('test_service', function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }

    public function testOpenCircuitRejectsCalls(): void
    {
        $mockStmt = Mockery::mock(PDOStatement::class);
        $mockStmt->shouldReceive('fetch')->andReturn([
            'service_name' => 'test_service',
            'state' => 'open',
            'failure_count' => 3,
            'last_failure_at' => date('Y-m-d H:i:s'),
            'opened_at' => date('Y-m-d H:i:s'),
            'half_open_at' => null,
        ]);

        $this->mockDb->shouldReceive('execute')->andReturn($mockStmt);

        $this->expectException(CircuitOpenException::class);

        $this->circuitBreaker->call('test_service', function () {
            return 'should not reach here';
        });
    }

    public function testCircuitUsesDefaultConfigForUnknownService(): void
    {
        $mockStmt = Mockery::mock(PDOStatement::class);
        $mockStmt->shouldReceive('fetch')->andReturn(false);
        $this->mockDb->shouldReceive('execute')->andReturn($mockStmt);

        $result = $this->circuitBreaker->call('unknown_service', function () {
            return 'works';
        });

        $this->assertEquals('works', $result);
    }
}
