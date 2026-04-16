<?php

namespace Spark\Database;

use Spark\Application;

class Migrator
{
    protected Connection $connection;
    protected string $path;

    public function __construct(Application $app)
    {
        $this->connection = $app->make(Connection::class);
        $this->path = $app->basePath . '/database/migrations';
        $this->ensureMigrationsTable();
    }

    protected function ensureMigrationsTable(): void
    {
        if (Schema::hasTable('migrations')) return;
        Schema::create('migrations', function (Blueprint $t) {
            $t->id();
            $t->string('migration');
            $t->integer('batch');
        });
    }

    /** @return string[] names of applied migrations (old \u2192 new) */
    public function applied(): array
    {
        $rows = $this->connection->select('SELECT migration FROM migrations ORDER BY id ASC');
        return array_column($rows, 'migration');
    }

    /** @return string[] migration filenames to run (sorted) */
    public function pending(): array
    {
        if (!is_dir($this->path)) return [];
        $files = glob($this->path . '/*.php') ?: [];
        $names = array_map(fn($f) => basename($f, '.php'), $files);
        sort($names);
        return array_values(array_diff($names, $this->applied()));
    }

    public function run(callable $output): int
    {
        $pending = $this->pending();
        if (!$pending) {
            $output('Nothing to migrate.');
            return 0;
        }

        $batch = $this->nextBatch();
        foreach ($pending as $name) {
            $instance = $this->resolve($name);
            $output("Migrating: $name");
            $instance->up();
            $this->connection->statement(
                'INSERT INTO migrations (migration, batch) VALUES (?, ?)',
                [$name, $batch]
            );
            $output("Migrated:  $name");
        }
        return count($pending);
    }

    public function rollback(callable $output): int
    {
        $batch = (int) ($this->connection->selectOne('SELECT MAX(batch) as m FROM migrations')['m'] ?? 0);
        if ($batch === 0) {
            $output('Nothing to rollback.');
            return 0;
        }

        $rows = $this->connection->select('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC', [$batch]);
        foreach ($rows as $row) {
            $name = $row['migration'];
            $instance = $this->resolve($name);
            $output("Rolling back: $name");
            $instance->down();
            $this->connection->affectingStatement('DELETE FROM migrations WHERE migration = ?', [$name]);
            $output("Rolled back:  $name");
        }
        return count($rows);
    }

    protected function nextBatch(): int
    {
        $row = $this->connection->selectOne('SELECT MAX(batch) as m FROM migrations');
        return (int) ($row['m'] ?? 0) + 1;
    }

    protected function resolve(string $name): Migration
    {
        $file = $this->path . '/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Migration file not found: $file");
        }
        $instance = require $file;
        if (!$instance instanceof Migration) {
            throw new \RuntimeException("Migration [$name] must return a Migration instance.");
        }
        return $instance;
    }
}
