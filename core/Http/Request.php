<?php

namespace Spark\Http;

class Request
{
    public array $query;
    public array $post;
    public array $server;
    public array $cookies;
    public array $files;
    public array $headers;
    public array $attributes = [];
    protected ?array $json = null;
    protected string $body;

    public function __construct(array $query, array $post, array $server, array $cookies, array $files, string $body = '')
    {
        $this->query = $query;
        $this->post = $post;
        $this->server = $server;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->body = $body;
        $this->headers = $this->extractHeaders($server);
    }

    public static function capture(): self
    {
        return new self(
            $_GET,
            $_POST,
            $_SERVER,
            $_COOKIE,
            $_FILES,
            file_get_contents('php://input') ?: ''
        );
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($this->post['_method'])) {
            return strtoupper($this->post['_method']);
        }
        return $method;
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . trim($path, '/');
    }

    public function url(): string
    {
        $scheme = ($this->server['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    public function isJson(): bool
    {
        $ct = $this->header('content-type', '');
        return str_contains((string) $ct, 'application/json');
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('accept', '');
        return $this->isJson() || str_contains((string) $accept, 'application/json');
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $decoded = json_decode($this->body, true);
            $this->json = is_array($decoded) ? $decoded : [];
        }
        return $key === null ? $this->json : ($this->json[$key] ?? $default);
    }

    public function all(): array
    {
        $data = array_merge($this->query, $this->post);
        if ($this->isJson()) {
            $data = array_merge($data, $this->json() ?? []);
        }
        return $data;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    protected function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = $v;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $server['CONTENT_LENGTH'];
        }
        return $headers;
    }
}
