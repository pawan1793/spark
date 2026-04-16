<?php

namespace Spark\Middleware;

use Closure;
use Spark\Http\Request;
use Spark\Http\Response;

/**
 * Redirect plain-HTTP requests to HTTPS in non-local environments.
 * Pair this with a Strict-Transport-Security header (Response already sets
 * one when the connection is HTTPS) so browsers remember to use TLS.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PHP_SAPI === 'cli') {
            return $next($request);
        }

        $env = function_exists('config') ? (string) config('app.env', 'production') : 'production';
        if ($env === 'local' || $env === 'testing') {
            return $next($request);
        }

        $isHttps = (($request->server['HTTPS'] ?? 'off') !== 'off')
            || (($request->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($request->server['SERVER_PORT'] ?? 0) === 443);

        if ($isHttps) {
            return $next($request);
        }

        $host = $request->server['HTTP_HOST'] ?? 'localhost';
        $uri = $request->server['REQUEST_URI'] ?? '/';
        return (new Response())->redirect('https://' . $host . $uri, 301, true);
    }
}
