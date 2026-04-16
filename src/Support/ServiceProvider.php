<?php

namespace Spark\Support;

use Spark\Application;

abstract class ServiceProvider
{
    public function __construct(protected Application $app) {}

    /**
     * Register bindings into the container.
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services (runs after all providers are registered).
     */
    public function boot(): void {}
}
