<?php

namespace Spark\Database;

use PDO;
use PDOException;

class Connection
{
    protected ?PDO $pdo = null;
    protected array $config;
    protected string $basePath;

    public function __construct(array $config, string $basePath)
    {
        $this->config = $config;
        $this->basePath = $basePath;
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }

    protected function connect(): PDO
    {
        $driver = $this->config['default'] ?? 'sqlite';
        $conn = $this->config['connections'][$driver] ?? [];

        $dsn = $this->buildDsn($driver, $conn);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            return new PDO($dsn, $conn['username'] ?? null, $conn['password'] ?? null, $options);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function buildDsn(string $driver, array $conn): string
    {
        return match ($driver) {
            'sqlite' => 'sqlite:' . $this->resolveSqlitePath($conn['database'] ?? 'storage/database.sqlite'),
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $conn['host'] ?? '127.0.0.1',
                $conn['port'] ?? 3306,
                $conn['database'] ?? '',
                $conn['charset'] ?? 'utf8mb4'
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $conn['host'] ?? '127.0.0.1',
                $conn['port'] ?? 5432,
                $conn['database'] ?? ''
            ),
            default => throw new \RuntimeException("Unsupported DB driver [$driver]"),
        };
    }

    protected function resolveSqlitePath(string $path): string
    {
        if ($path === ':memory:' || str_starts_with($path, '/')) {
            return $path;
        }
        $full = $this->basePath . '/' . ltrim($path, '/');
        if (!is_file($full)) {
            @touch($full);
        }
        return $full;
    }

    public function driver(): string
    {
        return $this->config['default'] ?? 'sqlite';
    }

    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $stmt = $this->pdo()->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function insert(string $sql, array $bindings = []): string
    {
        $this->statement($sql, $bindings);
        return $this->pdo()->lastInsertId();
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }
}
