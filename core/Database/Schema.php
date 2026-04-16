<?php

namespace Spark\Database;

use Spark\Application;

class Schema
{
    public static function create(string $table, \Closure $callback): void
    {
        $conn = self::conn();
        $blueprint = new Blueprint($table, $conn->driver());
        $callback($blueprint);

        $conn->statement($blueprint->toSql());
        foreach ($blueprint->extraSql() as $sql) {
            $conn->statement($sql);
        }
    }

    public static function drop(string $table): void
    {
        self::conn()->statement("DROP TABLE IF EXISTS $table");
    }

    public static function dropIfExists(string $table): void
    {
        self::drop($table);
    }

    public static function hasTable(string $table): bool
    {
        $conn = self::conn();
        if ($conn->driver() === 'sqlite') {
            $row = $conn->selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
        } else {
            $row = $conn->selectOne("SHOW TABLES LIKE ?", [$table]);
        }
        return $row !== null;
    }

    protected static function conn(): Connection
    {
        return Application::getApp()->make(Connection::class);
    }
}
