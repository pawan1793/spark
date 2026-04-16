<?php

namespace Spark\Middleware;

use Closure;
use Spark\Http\HttpException;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Http\Session;

class VerifyCsrfToken
{
    /**
     * HTTP methods that mutate state and therefore require a CSRF token.
     */
    protected const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * URI patterns/prefixes that should be excluded from CSRF verification.
     * Supports exact paths and fnmatch-style wildcards (e.g. 'api/*').
     * Override by extending this class in the application.
     */
    protected array $except = [];

    /**
     * When true, all routes whose path starts with $apiPrefix are exempt.
     */
    protected bool $exceptApiRoutes = false;

    /**
     * The URI prefix used to identify API routes when $exceptApiRoutes is true.
     */
    protected string $apiPrefix = 'api';

    public function handle(Request $request, Closure $next): Response
    {
        Session::start();

        if (!in_array($request->method(), self::PROTECTED_METHODS, true) || $this->isExcepted($request)) {
            return $next($request);
        }

        $token = $request->input('_token')
            ?? $request->header('x-csrf-token')
            ?? $request->header('x-xsrf-token');

        if (!is_string($token) || !Session::verifyCsrfToken($token)) {
            throw new HttpException(419, 'CSRF token mismatch.');
        }

        return $next($request);
    }

    protected function isExcepted(Request $request): bool
    {
        // Per-route withoutCsrf() flag set by the router
        if ($request->attribute('_csrf_exempt') === true) {
            return true;
        }

        $path = ltrim($request->path(), '/');

        // Exclude all routes under the configured API prefix
        if ($this->exceptApiRoutes) {
            $prefix = trim($this->apiPrefix, '/') . '/';
            if (str_starts_with($path . '/', $prefix) || $path === trim($this->apiPrefix, '/')) {
                return true;
            }
        }

        // Explicit except list (exact match or wildcard)
        $rawPath = $request->path();
        foreach ($this->except as $pattern) {
            if ($pattern === $rawPath || fnmatch($pattern, $rawPath)) {
                return true;
            }
        }

        return false;
    }
}
