<?php

declare(strict_types=1);

namespace Tests\Sanity;

use PHPUnit\Framework\TestCase;
use App\Database\Connection;
use App\Logging\StructuredLogger;
use Mockery;

class DatabaseConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testCanConnectToDatabase(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_NAME') ?: 'procom'
        );
        $user = getenv('DB_USER') ?: 'procom';
        $pass = getenv('DB_PASS') ?: '';

        $logger = Mockery::mock(StructuredLogger::class)->shouldIgnoreMissing();

        try {
            $db = new Connection($dsn, $user, $pass, $logger);
            $stmt = $db->execute('SELECT 1 as test');
            $result = $stmt->fetch();

            $this->assertEquals(1, $result['test']);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    public function testMigrationsTableExists(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_NAME') ?: 'procom'
        );
        $user = getenv('DB_USER') ?: 'procom';
        $pass = getenv('DB_PASS') ?: '';

        $logger = Mockery::mock(StructuredLogger::class)->shouldIgnoreMissing();

        try {
            $db = new Connection($dsn, $user, $pass, $logger);
            $stmt = $db->execute("SHOW TABLES LIKE 'migrations'");
            $result = $stmt->fetchAll();

            $this->assertNotEmpty($result, 'Migrations table should exist');
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }
}
