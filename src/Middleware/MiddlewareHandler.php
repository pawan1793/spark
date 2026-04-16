<?php

namespace Spark\Middleware;

use Closure;
use Spark\Application;
use Spark\Http\Request;
use Spark\Http\Response;

class MiddlewareHandler
{
    public function __construct(
        protected Application $app,
        protected array $middleware,
        protected Closure $destination
    ) {}

    public function handle(Request $request): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function (Closure $next, string $middleware) {
                return function (Request $request) use ($next, $middleware) {
                    $instance = $this->app->make($middleware);
                    if (!method_exists($instance, 'handle')) {
                        throw new \RuntimeException("Middleware [$middleware] must define handle().");
                    }
                    return $instance->handle($request, $next);
                };
            },
            $this->destination
        );

        return $pipeline($request);
    }
}
