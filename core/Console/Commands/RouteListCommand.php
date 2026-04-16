<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;
use Spark\Router\Router;

class RouteListCommand extends Command
{
    public string $description = 'Display all registered routes.';

    public function handle(array $args, array $options): int
    {
        $this->app->loadRoutes();
        $router = $this->app->make(Router::class);
        $routes = $router->all();

        if (!$routes) {
            $this->warn('No routes registered.');
            return 0;
        }

        $this->line(sprintf("%-8s %-40s %s", 'METHOD', 'URI', 'ACTION'));
        $this->line(str_repeat('-', 72));
        foreach ($routes as $route) {
            $action = $this->stringify($route->action);
            $this->line(sprintf("%-8s %-40s %s", $route->method, $route->uri, $action));
        }
        return 0;
    }

    protected function stringify(mixed $action): string
    {
        if (is_string($action)) return $action;
        if (is_array($action)) return ($action[0] ?? '?') . '@' . ($action[1] ?? '?');
        if ($action instanceof \Closure) return 'Closure';
        return 'unknown';
    }
}
