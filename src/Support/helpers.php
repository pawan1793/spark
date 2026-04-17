<?php

use Spark\Application;
use Spark\Config\Env;
use Spark\Http\Response;
use Spark\Http\Session;
use Spark\Support\Logger;
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
        if (!is_string($value)) {
            return $value;
        }
        return match (strtolower($value)) {
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
    /**
     * Issue a same-origin redirect. Set $allowExternal=true only for
     * statically defined external URLs (never user-supplied values).
     */
    function redirect(string $url, int $status = 302, bool $allowExternal = false): Response
    {
        return (new Response())->redirect($url, $status, $allowExternal);
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new \Spark\Http\HttpException($status, $message);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = htmlspecialchars(Session::csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('e')) {
    /**
     * Escape a value for safe output in HTML.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csp_nonce')) {
    /**
     * Per-request Content-Security-Policy nonce. Use this on inline
     * <script> / <style> tags so they are allowed by the strict CSP:
     *     <style nonce="{{ csp_nonce() }}">...</style>
     */
    function csp_nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        }
        return $nonce;
    }
}

if (!function_exists('bcrypt')) {
    /**
     * Hash a password using PHP's current recommended algorithm.
     * Callers should verify with password_verify() — never compare hashes
     * with == / === (timing attacks, algorithm rehashing).
     */
    function bcrypt(string $password, array $options = []): string
    {
        return password_hash($password, PASSWORD_DEFAULT, $options);
    }
}

if (!function_exists('logger')) {
    function logger(?string $message = null, array $context = []): Logger
    {
        $logger = app(Logger::class);
        if ($message !== null) {
            $logger->info($message, $context);
        }
        return $logger;
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        // Only expose var_dump output when debug mode is on — otherwise emit
        // a plain 500 so dev leftovers can't leak internals in production.
        $debug = false;
        try {
            $debug = (bool) Application::getApp()->config('app.debug', false);
        } catch (\Throwable) {
            $debug = false;
        }
        if (!$debug) {
            http_response_code(500);
            echo 'Internal Server Error';
            exit(1);
        }
        foreach ($vars as $v) {
            echo '<pre style="background:#1a1a1a;color:#0f0;padding:1rem;border-radius:6px;font-family:ui-monospace">';
            var_dump($v);
            echo '</pre>';
        }
        exit(1);
    }
}
