<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOStatement;
use App\Logging\StructuredLogger;

/**
 * Database connection wrapper enforcing parameterized queries only.
 * Connects via MySQL Router for automatic failover.
 */
class Connection
{
    private PDO $pdo;
    private StructuredLogger $logger;

    public function __construct(string $dsn, string $user, string $pass, StructuredLogger $logger)
    {
        $this->logger = $logger;

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ]);

        $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /**
     * Execute a parameterized query. This is the ONLY way to query the database.
     * Raw queries without parameters are not exposed.
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->logger->debug('Database query executed', [
            'duration_ms' => $durationMs,
            'row_count' => $stmt->rowCount(),
        ]);

        return $stmt;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
