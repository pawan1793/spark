<?php

namespace Spark\Middleware;

use Closure;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Http\Session;

class StartSession
{
    public function handle(Request $request, Closure $next): Response
    {
        Session::start();
        // Eagerly generate a CSRF token so forms can render @csrf even on
        // the very first GET of the session.
        Session::csrfToken();
        return $next($request);
    }
}
