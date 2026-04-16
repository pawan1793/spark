<?php

namespace Spark\Database;

use InvalidArgumentException;

class QueryBuilder
{
    protected string $table;
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?Connection $connection;
    protected ?string $modelClass = null;

    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = self::assertIdentifier($table, 'table');
    }

    public function setModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    public function select(string|array $columns): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        foreach ($cols as $c) {
            if ($c !== '*') {
                self::assertIdentifier($c, 'column');
            }
        }
        $this->columns = $cols;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        self::assertIdentifier($column, 'column');
        $operator = self::assertOperator($operator);
        $boolean = strtoupper($boolean) === 'OR' ? 'OR' : 'AND';
        $this->wheres[] = compact('column', 'operator', 'value', 'boolean');
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values): self
    {
        self::assertIdentifier($column, 'column');
        if (empty($values)) {
            // WHERE col IN () is invalid; force a no-match clause
            $this->wheres[] = [
                'column' => '1',
                'operator' => '=',
                'value' => '0',
                'boolean' => 'AND',
                'raw' => true,
            ];
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => "($placeholders)",
            'boolean' => 'AND',
            'raw' => true,
        ];
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        self::assertIdentifier($column, 'column');
        $direction = strtoupper($direction);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new InvalidArgumentException("Invalid order direction: $direction");
        }
        $this->orders[] = self::quote($column) . ' ' . $direction;
        return $this;
    }

    public function limit(int $n): self
    {
        if ($n < 0) {
            throw new InvalidArgumentException('LIMIT must be non-negative.');
        }
        $this->limit = $n;
        return $this;
    }

    public function offset(int $n): self
    {
        if ($n < 0) {
            throw new InvalidArgumentException('OFFSET must be non-negative.');
        }
        $this->offset = $n;
        return $this;
    }

    public function toSql(): string
    {
        $cols = array_map(fn($c) => $c === '*' ? '*' : self::quote($c), $this->columns);
        $sql = 'SELECT ' . implode(', ', $cols) . ' FROM ' . self::quote($this->table);
        $sql .= $this->buildWhere();
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . (int) $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . (int) $this->offset;
        }
        return $sql;
    }

    protected function buildWhere(): string
    {
        if (!$this->wheres) return '';
        $parts = [];
        foreach ($this->wheres as $i => $w) {
            $col = isset($w['raw']) && $w['column'] === '1' ? '1' : self::quote($w['column']);
            $clause = isset($w['raw'])
                ? "{$col} {$w['operator']} {$w['value']}"
                : "{$col} {$w['operator']} ?";
            if ($i === 0) {
                $parts[] = $clause;
            } else {
                $parts[] = $w['boolean'] . ' ' . $clause;
            }
        }
        return ' WHERE ' . implode(' ', $parts);
    }

    public function get(): array
    {
        $rows = $this->connection->select($this->toSql(), $this->bindings);
        if ($this->modelClass) {
            return array_map(fn($row) => ($this->modelClass)::hydrate($row), $rows);
        }
        return $rows;
    }

    public function first(): mixed
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function find(int|string $id, string $pk = 'id'): mixed
    {
        return $this->where($pk, '=', $id)->first();
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) as c FROM ' . self::quote($this->table) . $this->buildWhere();
        $row = $this->connection->selectOne($sql, $this->bindings);
        return (int) ($row['c'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $data): string
    {
        $columns = array_keys($data);
        foreach ($columns as $c) {
            self::assertIdentifier($c, 'column');
        }
        $quotedCols = array_map(fn($c) => self::quote($c), $columns);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . self::quote($this->table)
            . ' (' . implode(',', $quotedCols) . ') VALUES (' . $placeholders . ')';
        return $this->connection->insert($sql, array_values($data));
    }

    public function update(array $data): int
    {
        $set = [];
        $values = [];
        foreach ($data as $col => $val) {
            self::assertIdentifier($col, 'column');
            $set[] = self::quote($col) . ' = ?';
            $values[] = $val;
        }
        $sql = 'UPDATE ' . self::quote($this->table) . ' SET ' . implode(', ', $set) . $this->buildWhere();
        return $this->connection->affectingStatement($sql, array_merge($values, $this->bindings));
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . self::quote($this->table) . $this->buildWhere();
        return $this->connection->affectingStatement($sql, $this->bindings);
    }

    /**
     * Validate that a string is a safe SQL identifier.
     * Allows optional "table.column" form. Rejects anything with whitespace,
     * quotes, semicolons, or other SQL metacharacters.
     */
    public static function assertIdentifier(string $identifier, string $kind = 'identifier'): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier)) {
            throw new InvalidArgumentException("Invalid $kind: " . $identifier);
        }
        return $identifier;
    }

    /**
     * Wrap an identifier in backticks (works for MySQL/SQLite; PostgreSQL uses
     * double quotes but treats backticks as strings — so we keep driver-agnostic
     * behavior by only quoting when safe. With identifier validation above,
     * quoting is primarily defense-in-depth against reserved words.)
     */
    protected static function quote(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            return implode('.', array_map(fn($p) => '`' . $p . '`', explode('.', $identifier)));
        }
        return '`' . $identifier . '`';
    }

    protected static function assertOperator(string $operator): string
    {
        $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];
        $up = strtoupper($operator);
        if (!in_array($up, $allowed, true)) {
            throw new InvalidArgumentException("Invalid SQL operator: $operator");
        }
        return $up === 'LIKE' || $up === 'NOT LIKE' || $up === 'IS' || $up === 'IS NOT' ? $up : $operator;
    }
}
