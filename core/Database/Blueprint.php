<?php

namespace Spark\Database;

class Blueprint
{
    protected array $columns = [];
    protected array $indexes = [];
    protected array $foreigns = [];

    public function __construct(public readonly string $table, public readonly string $driver)
    {
        QueryBuilder::assertIdentifier($table, 'table');
    }

    public function id(string $name = 'id'): self
    {
        QueryBuilder::assertIdentifier($name, 'column');
        $type = $this->driver === 'sqlite'
            ? "INTEGER PRIMARY KEY AUTOINCREMENT"
            : "BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        $this->columns[] = "`$name` $type";
        return $this;
    }

    public function string(string $name, int $length = 255, bool $nullable = false): self
    {
        QueryBuilder::assertIdentifier($name, 'column');
        if ($length < 1 || $length > 65535) {
            throw new \InvalidArgumentException("Invalid string length: $length");
        }
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` VARCHAR($length) $null";
        return $this;
    }

    public function text(string $name, bool $nullable = false): self
    {
        QueryBuilder::assertIdentifier($name, 'column');
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` TEXT $null";
        return $this;
    }

    public function integer(string $name, bool $nullable = false): self
    {
        QueryBuilder::assertIdentifier($name, 'column');
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` INTEGER $null";
        return $this;
    }

    public function bigInteger(string $name, bool $nullable = false): self
    {
        QueryBuilder::assertIdentifier($name, 'column');
        $type = $this->driver === 'sqlite' ? 'INTEGER' : 'BIGINT';
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` $type $null";
        return $this;
    }

    public function boolean(string $name, bool $default = false): self
    {
        QueryBuilder::assertIdentifier($name, 'column');
        $type = $this->driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)';
        $d = $default ? 1 : 0;
        $this->columns[] = "`$name` $type NOT NULL DEFAULT $d";
        return $this;
    }

    public function timestamp(string $name, bool $nullable = true): self
    {
        QueryBuilder::assertIdentifier($name, 'column');
        $null = $nullable ? 'NULL' : 'NOT NULL';
        $this->columns[] = "`$name` DATETIME $null";
        return $this;
    }

    public function timestamps(): self
    {
        $this->columns[] = "`created_at` DATETIME NULL";
        $this->columns[] = "`updated_at` DATETIME NULL";
        return $this;
    }

    public function foreignId(string $name): self
    {
        return $this->bigInteger($name);
    }

    public function unique(string|array $columns, ?string $name = null): self
    {
        $cols = (array) $columns;
        foreach ($cols as $c) {
            QueryBuilder::assertIdentifier($c, 'column');
        }
        $name ??= $this->table . '_' . implode('_', $cols) . '_unique';
        QueryBuilder::assertIdentifier($name, 'index');
        $quoted = implode(',', array_map(fn($c) => "`$c`", $cols));
        $this->indexes[] = "CONSTRAINT `$name` UNIQUE ($quoted)";
        return $this;
    }

    public function index(string|array $columns, ?string $name = null): self
    {
        $cols = (array) $columns;
        foreach ($cols as $c) {
            QueryBuilder::assertIdentifier($c, 'column');
        }
        $name ??= $this->table . '_' . implode('_', $cols) . '_idx';
        QueryBuilder::assertIdentifier($name, 'index');
        $quoted = implode(',', array_map(fn($c) => "`$c`", $cols));
        $this->indexes[] = "INDEX `$name` ($quoted)";
        return $this;
    }

    public function toSql(): string
    {
        $parts = $this->columns;
        if ($this->driver === 'sqlite') {
            // sqlite doesn't support inline INDEX in CREATE TABLE
            $parts = array_merge($parts, array_filter($this->indexes, fn($i) => !str_starts_with($i, 'INDEX')));
        } else {
            $parts = array_merge($parts, $this->indexes);
        }
        return 'CREATE TABLE `' . $this->table . "` (\n  " . implode(",\n  ", $parts) . "\n)";
    }

    public function extraSql(): array
    {
        // For sqlite: separate CREATE INDEX statements
        if ($this->driver !== 'sqlite') return [];
        $out = [];
        foreach ($this->indexes as $idx) {
            if (str_starts_with($idx, 'INDEX')) {
                if (preg_match('/INDEX\s+`([A-Za-z_][A-Za-z0-9_]*)`\s+\((.+)\)/', $idx, $m)) {
                    $out[] = "CREATE INDEX `{$m[1]}` ON `{$this->table}` ({$m[2]})";
                }
            }
        }
        return $out;
    }
}
