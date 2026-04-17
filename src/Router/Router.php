<?php

namespace Spark\Router;

use Spark\Application;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Http\HttpException;
use Spark\Middleware\MiddlewareHandler;

class Router
{
    /** @var Route[] */
    protected array $routes = [];
    protected array $groupStack = [];
    protected array $namedRoutes = [];

    public function __construct(protected Application $app) {}

    public function get(string $uri, mixed $action): Route { return $this->addRoute('GET', $uri, $action); }
    public function post(string $uri, mixed $action): Route { return $this->addRoute('POST', $uri, $action); }
    public function put(string $uri, mixed $action): Route { return $this->addRoute('PUT', $uri, $action); }
    public function patch(string $uri, mixed $action): Route { return $this->addRoute('PATCH', $uri, $action); }
    public function delete(string $uri, mixed $action): Route { return $this->addRoute('DELETE', $uri, $action); }
    public function any(string $uri, mixed $action): Route { return $this->addRoute('ANY', $uri, $action); }

    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    protected function addRoute(string $method, string $uri, mixed $action): Route
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }

        $uri = $prefix . '/' . ltrim($uri, '/');
        $route = new Route($method, $uri, $action);
        $route->middleware = $middleware;
        $this->routes[] = $route;
        return $route;
    }

    /** @return Route[] */
    public function all(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        $methodAllowed = false;

        foreach ($this->routes as $route) {
            $params = [];
            if ($route->matches($method, $path, $params)) {
                foreach ($params as $k => $v) {
                    $request->setAttribute($k, $v);
                }
                return $this->runWithMiddleware($route, $request, $params);
            }
            // Track 405
            if (preg_match($route->getRegex(), $path)) {
                $methodAllowed = true;
            }
        }

        if ($methodAllowed) {
            throw new HttpException(405);
        }
        throw new HttpException(404, "Route [$method $path] not found.");
    }

    protected function runWithMiddleware(Route $route, Request $request, array $params): Response
    {
        if ($route->csrfExempt) {
            $request->setAttribute('_csrf_exempt', true);
        }

        $handler = new MiddlewareHandler($this->app, $route->middleware, function (Request $req) use ($route, $params) {
            return $this->runAction($route->action, $req, $params);
        });
        return $handler->handle($request);
    }

    protected function runAction(mixed $action, Request $request, array $params): Response
    {
        $result = null;

        if (is_callable($action) && !is_array($action)) {
            $result = $this->app->call($action, array_merge(['request' => $request], $params));
        } elseif (is_array($action)) {
            [$class, $method] = $action;
            $result = $this->app->call([$class, $method], array_merge(['request' => $request], $params));
        } elseif (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action);
            $result = $this->app->call([$class, $method], array_merge(['request' => $request], $params));
        } else {
            throw new \RuntimeException('Invalid route action.');
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_object($result)) {
            return (new Response())->json($result);
        }
        return (new Response())->html((string) $result);
    }
}
