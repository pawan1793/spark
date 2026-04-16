<?php

namespace Spark\Router;

class Route
{
    public array $middleware = [];
    public ?string $name = null;
    public bool $csrfExempt = false;
    protected string $regex;
    protected array $paramNames = [];

    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly mixed $action
    ) {
        $this->compile();
    }

    protected function compile(): void
    {
        $uri = '/' . trim($this->uri, '/');
        $names = [];
        $pattern = preg_replace_callback('/\{(\w+)(\?)?\}/', function ($m) use (&$names) {
            $names[] = $m[1];
            return isset($m[2]) ? '(?:/([^/]+))?' : '([^/]+)';
        }, $uri);

        $pattern = '#^' . $pattern . '$#';
        $this->regex = $pattern;
        $this->paramNames = $names;
    }

    public function matches(string $method, string $path, array &$params = []): bool
    {
        if (strcasecmp($this->method, $method) !== 0 && $this->method !== 'ANY') {
            return false;
        }
        if (!preg_match($this->regex, $path, $matches)) {
            return false;
        }

        array_shift($matches);
        $params = [];
        foreach ($this->paramNames as $i => $name) {
            $params[$name] = $matches[$i] ?? null;
        }
        return true;
    }

    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withoutCsrf(): self
    {
        $this->csrfExempt = true;
        return $this;
    }
}
