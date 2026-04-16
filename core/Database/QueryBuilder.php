<?php

namespace Spark\Database;

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
        $this->table = $table;
    }

    public function setModel(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    public function select(string|array $columns): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    public function where(string $column, string $operator, mixed $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
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
        $this->orders[] = "$column " . strtoupper($direction);
        return $this;
    }

    public function limit(int $n): self
    {
        $this->limit = $n;
        return $this;
    }

    public function offset(int $n): self
    {
        $this->offset = $n;
        return $this;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table;
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
            $clause = isset($w['raw'])
                ? "{$w['column']} {$w['operator']} {$w['value']}"
                : "{$w['column']} {$w['operator']} ?";
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
        $sql = 'SELECT COUNT(*) as c FROM ' . $this->table . $this->buildWhere();
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
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
        return $this->connection->insert($sql, array_values($data));
    }

    public function update(array $data): int
    {
        $set = [];
        $values = [];
        foreach ($data as $col => $val) {
            $set[] = "$col = ?";
            $values[] = $val;
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $set) . $this->buildWhere();
        return $this->connection->affectingStatement($sql, array_merge($values, $this->bindings));
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table . $this->buildWhere();
        return $this->connection->affectingStatement($sql, $this->bindings);
    }
}
