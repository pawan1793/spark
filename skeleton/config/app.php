<?php

return [
    'name' => env('APP_NAME', 'Spark'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url' => env('APP_URL', 'http://localhost'),
    'key' => env('APP_KEY'),
    'log_level' => env('LOG_LEVEL', 'debug'),

    'web_middleware' => [
        \Spark\Middleware\StartSession::class,
        \Spark\Middleware\VerifyCsrfToken::class,
    ],

    'api_middleware' => [],

    'trusted_proxies' => [],
];
