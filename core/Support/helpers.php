<?php

use Spark\Application;
use Spark\Config\Env;
use Spark\Http\Response;
use Spark\View\View;

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $app = Application::getApp();
        return $abstract ? $app->make($abstract) : $app;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Application::getApp()->config($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = Env::get($key, $default);
        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return Application::getApp()->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = []): Response
    {
        $html = app(View::class)->render($name, $data);
        return (new Response())->html($html);
    }
}

if (!function_exists('response')) {
    function response(): Response
    {
        return new Response();
    }
}

if (!function_exists('json')) {
    function json(mixed $data, int $status = 200): Response
    {
        return (new Response())->json($data, $status);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): Response
    {
        return (new Response())->redirect($url, $status);
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new \Spark\Http\HttpException($status, $message);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $v) {
            echo '<pre style="background:#1a1a1a;color:#0f0;padding:1rem;border-radius:6px;font-family:ui-monospace">';
            var_dump($v);
            echo '</pre>';
        }
        exit(1);
    }
}
