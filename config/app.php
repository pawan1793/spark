<?php

return [
    'name' => env('APP_NAME', 'Spark'),
    'env' => env('APP_ENV', 'production'),
    // Default to false so a missing / misconfigured .env never flips debug on.
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url' => env('APP_URL', 'http://localhost'),
    'key' => env('APP_KEY'),
    'log_level' => env('LOG_LEVEL', 'debug'),

    // Middleware applied to all web routes by default. StartSession must
    // come before VerifyCsrfToken.
    'web_middleware' => [
        \Spark\Middleware\StartSession::class,
        \Spark\Middleware\VerifyCsrfToken::class,
    ],

    // Middleware applied to all /api routes by default.
    'api_middleware' => [],

    /*
     * Trusted proxy IP addresses. Only when REMOTE_ADDR matches one of
     * these will X-Forwarded-For / X-Real-IP be honored. An empty array
     * means "never trust proxy headers" — the safest default.
     */
    'trusted_proxies' => [],
];
