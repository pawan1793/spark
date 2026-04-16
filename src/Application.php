<?php

namespace Spark;

use Spark\Config\Env;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Router\Router;
use Spark\Database\Connection;
use Spark\View\View;
use Spark\Support\Logger;
use Spark\Support\ErrorHandler;
use Spark\Support\ServiceProvider;

class Application extends Container
{
    public readonly string $basePath;
    protected array $config = [];
    protected static ?Application $app = null;
    /** @var ServiceProvider[] */
    protected array $providers = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        self::$app = $this;
        Container::setInstance($this);

        $this->bootstrap();
    }

    public static function getApp(): self
    {
        if (!self::$app) {
            throw new \RuntimeException('Application not bootstrapped.');
        }
        return self::$app;
    }

    protected function bootstrap(): void
    {
        Env::load($this->basePath . '/.env');
        $this->loadConfig();

        ErrorHandler::register($this->config('app.debug', false), $this);

        $this->instance(self::class, $this);
        $this->instance('app', $this);
        $this->instance('config', $this->config);

        $this->singleton(Logger::class, fn() => new Logger(
            $this->basePath . '/storage/logs/spark.log',
            $this->config('app.log_level', 'debug')
        ));

        $this->singleton(Router::class, fn() => new Router($this));
        $this->singleton(Connection::class, fn() => new Connection($this->config('database'), $this->basePath));
        $this->singleton(View::class, fn() => new View(
            $this->basePath . '/resources/views',
            $this->basePath . '/storage/cache/views'
        ));

        $this->ensureStorageDirs();
    }

    public function register(string $providerClass): self
    {
        $provider = new $providerClass($this);
        $provider->register();
        $this->providers[] = $provider;
        return $this;
    }

    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    protected function loadConfig(): void
    {
        $dir = $this->basePath . '/config';
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.php') as $file) {
            $key = basename($file, '.php');
            $this->config[$key] = require $file;
        }
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public function configAll(): array
    {
        return $this->config;
    }

    public function loadRoutes(): void
    {
        $router = $this->make(Router::class);
        $webFile = $this->basePath . '/routes/web.php';
        $apiFile = $this->basePath . '/routes/api.php';

        $webMiddleware = (array) $this->config('app.web_middleware', [
            \Spark\Middleware\StartSession::class,
            \Spark\Middleware\VerifyCsrfToken::class,
        ]);
        $apiMiddleware = (array) $this->config('app.api_middleware', []);

        if (is_file($webFile)) {
            $router->group(['middleware' => $webMiddleware], function ($router) use ($webFile) {
                (function () use ($router, $webFile) {
                    require $webFile;
                })();
            });
        }
        if (is_file($apiFile)) {
            $router->group(['prefix' => 'api', 'middleware' => $apiMiddleware], function ($router) use ($apiFile) {
                (function () use ($router, $apiFile) {
                    require $apiFile;
                })();
            });
        }
    }

    public function handle(Request $request): Response
    {
        $this->instance(Request::class, $request);
        $this->loadRoutes();
        return $this->make(Router::class)->dispatch($request);
    }

    public function run(): void
    {
        $request = Request::capture();
        $response = $this->handle($request);
        $response->send();
    }

    protected function ensureStorageDirs(): void
    {
        $dirs = [
            $this->basePath . '/storage/logs',
            $this->basePath . '/storage/cache/views',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                // 0770 — writable by owner and group only. Web servers
                // typically share a group, not global write access.
                @mkdir($dir, 0770, true);
            }
        }
    }
}
