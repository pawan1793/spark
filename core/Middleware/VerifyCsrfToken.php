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
     * URI prefixes that should be excluded from CSRF verification. Override
     * by extending this class in the application.
     */
    protected array $except = [];

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
        $path = $request->path();
        foreach ($this->except as $pattern) {
            if ($pattern === $path || fnmatch($pattern, $path)) {
                return true;
            }
        }
        return false;
    }
}
