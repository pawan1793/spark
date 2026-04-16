<?php

namespace Spark\Middleware;

use Closure;
use Spark\Http\Request;
use Spark\Http\Response;

/**
 * Strict CORS middleware. By default nothing is allowed; configure the
 * application's config('cors.*') entries to opt into specific origins.
 *
 * Never set Access-Control-Allow-Origin: * together with
 * Access-Control-Allow-Credentials: true — that combination is explicitly
 * disallowed by the CORS spec and is a common misconfiguration.
 */
class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = (array) (function_exists('config') ? config('cors.allowed_origins', []) : []);
        $allowedMethods = (array) (function_exists('config') ? config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']) : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
        $allowedHeaders = (array) (function_exists('config') ? config('cors.allowed_headers', ['Content-Type', 'Authorization', 'X-CSRF-Token', 'X-Requested-With']) : ['Content-Type', 'Authorization', 'X-CSRF-Token', 'X-Requested-With']);
        $exposeHeaders = (array) (function_exists('config') ? config('cors.exposed_headers', []) : []);
        $maxAge = (int) (function_exists('config') ? config('cors.max_age', 0) : 0);
        $credentials = (bool) (function_exists('config') ? config('cors.supports_credentials', false) : false);

        $origin = $request->header('origin');

        // Preflight short-circuit.
        if ($request->method() === 'OPTIONS') {
            $response = (new Response())->status(204);
            $this->applyCors($response, $origin, $allowedOrigins, $allowedMethods, $allowedHeaders, $exposeHeaders, $maxAge, $credentials);
            return $response;
        }

        $response = $next($request);
        $this->applyCors($response, $origin, $allowedOrigins, $allowedMethods, $allowedHeaders, $exposeHeaders, $maxAge, $credentials);
        return $response;
    }

    protected function applyCors(
        Response $response,
        ?string $origin,
        array $allowedOrigins,
        array $allowedMethods,
        array $allowedHeaders,
        array $exposeHeaders,
        int $maxAge,
        bool $credentials
    ): void {
        if (!$origin) {
            return;
        }

        $match = null;
        if (in_array($origin, $allowedOrigins, true)) {
            $match = $origin;
        } elseif (in_array('*', $allowedOrigins, true) && !$credentials) {
            $match = '*';
        }
        if ($match === null) {
            return;
        }

        $response->header('Access-Control-Allow-Origin', $match);
        $response->header('Vary', 'Origin');
        $response->header('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
        $response->header('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
        if ($exposeHeaders) {
            $response->header('Access-Control-Expose-Headers', implode(', ', $exposeHeaders));
        }
        if ($maxAge > 0) {
            $response->header('Access-Control-Max-Age', (string) $maxAge);
        }
        if ($credentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }
}
