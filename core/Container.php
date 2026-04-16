<?php

namespace Spark;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    protected array $bindings = [];
    protected array $instances = [];
    protected static ?Container $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(self $container): void
    {
        self::$instance = $container;
    }

    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => $shared,
        ];
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? $abstract;

        $object = $concrete instanceof Closure
            ? $concrete($this, $parameters)
            : $this->build($concrete, $parameters);

        if ($binding && $binding['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    public function build(string $class, array $parameters = []): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Class [$class] does not exist.");
        }

        $reflector = new ReflectionClass($class);
        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Class [$class] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if (!$constructor) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->make($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new RuntimeException("Cannot resolve parameter \${$name} for [$class].");
        }

        return $reflector->newInstanceArgs($args);
    }

    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;
            $instance = is_object($class) ? $class : $this->make($class);
            $reflection = new \ReflectionMethod($instance, $method);
            $args = $this->resolveDependencies($reflection->getParameters(), $parameters);
            return $reflection->invokeArgs($instance, $args);
        }

        $reflection = new \ReflectionFunction($callback);
        $args = $this->resolveDependencies($reflection->getParameters(), $parameters);
        return $reflection->invokeArgs($args);
    }

    protected function resolveDependencies(array $params, array $overrides): array
    {
        $args = [];
        foreach ($params as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $overrides)) {
                $args[] = $overrides[$name];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->make($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            $args[] = null;
        }
        return $args;
    }
}
