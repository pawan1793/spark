<?php

namespace Spark\Database;

use Spark\Application;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    /** Attributes that may be mass-assigned via fill() / create() / update(). */
    protected static array $fillable = [];
    /** Attributes that must never be mass-assigned even if they appear in $fillable. */
    protected static array $guarded = ['id', 'created_at', 'updated_at'];
    protected static bool $timestamps = true;

    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Mass-assign attributes. Attributes are only accepted if they appear in
     * static::$fillable AND not in static::$guarded. An empty $fillable means
     * "no mass assignment allowed" — this is a deliberate whitelist default
     * that prevents privilege-escalation bugs (e.g. setting is_admin via a
     * JSON body). Use setAttribute() to assign values from trusted code.
     */
    public function fill(array $attributes): static
    {
        $fillable = static::$fillable;
        if (empty($fillable)) {
            return $this;
        }
        $guarded = static::$guarded;
        foreach ($attributes as $key => $value) {
            if (in_array($key, $fillable, true) && !in_array($key, $guarded, true)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * Set an attribute from trusted code, bypassing mass-assignment rules.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public static function hydrate(array $row): static
    {
        $instance = new static();
        $instance->attributes = $row;
        $instance->original = $row;
        $instance->exists = true;
        return $instance;
    }

    public static function getTable(): string
    {
        if (static::$table) return static::$table;
        $class = (new \ReflectionClass(static::class))->getShortName();
        return strtolower(self::pluralize($class));
    }

    protected static function pluralize(string $word): string
    {
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        }
        if (str_ends_with($word, 's')) {
            return $word . 'es';
        }
        return $word . 's';
    }

    public static function query(): QueryBuilder
    {
        $conn = Application::getApp()->make(Connection::class);
        return (new QueryBuilder($conn, static::getTable()))->setModel(static::class);
    }

    public static function all(): array { return static::query()->get(); }
    public static function find(int|string $id): ?static { return static::query()->find($id, static::$primaryKey); }
    public static function where(string $c, string $op, mixed $v = null): QueryBuilder
    {
        return func_num_args() === 2 ? static::query()->where($c, $op) : static::query()->where($c, $op, $v);
    }
    public static function first(): ?static { return static::query()->first(); }
    public static function count(): int { return static::query()->count(); }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public function save(): bool
    {
        $conn = Application::getApp()->make(Connection::class);
        $qb = new QueryBuilder($conn, static::getTable());
        $now = date('Y-m-d H:i:s');

        if ($this->exists) {
            $pk = static::$primaryKey;
            $data = $this->attributes;
            unset($data[$pk]);
            if (static::$timestamps) {
                $data['updated_at'] = $now;
            }
            $qb->where($pk, '=', $this->attributes[$pk])->update($data);
        } else {
            if (static::$timestamps) {
                $this->attributes['created_at'] ??= $now;
                $this->attributes['updated_at'] ??= $now;
            }
            $id = $qb->insert($this->attributes);
            if (!isset($this->attributes[static::$primaryKey])) {
                $this->attributes[static::$primaryKey] = $id;
            }
            $this->exists = true;
        }
        $this->original = $this->attributes;
        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists) return false;
        $conn = Application::getApp()->make(Connection::class);
        $pk = static::$primaryKey;
        (new QueryBuilder($conn, static::getTable()))
            ->where($pk, '=', $this->attributes[$pk])
            ->delete();
        $this->exists = false;
        return true;
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function __get(string $key): mixed
    {
        if (method_exists($this, $key)) {
            return $this->$key();
        }
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    // Relationships -----

    protected function hasOne(string $related, string $foreignKey, string $localKey = 'id'): ?Model
    {
        return $related::where($foreignKey, '=', $this->attributes[$localKey] ?? null)->first();
    }

    protected function hasMany(string $related, string $foreignKey, string $localKey = 'id'): array
    {
        return $related::where($foreignKey, '=', $this->attributes[$localKey] ?? null)->get();
    }

    protected function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): ?Model
    {
        $value = $this->attributes[$foreignKey] ?? null;
        if ($value === null) return null;
        return $related::where($ownerKey, '=', $value)->first();
    }

    public function jsonSerialize(): array { return $this->toArray(); }
}
