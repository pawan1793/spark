<?php

// Minimal PSR-4 autoloader — works without composer install.
// If vendor/autoload.php exists (from `composer install`), use that instead.

$base = dirname(__DIR__);

if (is_file($base . '/vendor/autoload.php')) {
    require $base . '/vendor/autoload.php';
    return;
}

spl_autoload_register(function (string $class) use ($base) {
    $prefixes = [
        'Spark\\' => $base . '/core/',
        'App\\' => $base . '/app/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) continue;
        $relative = substr($class, strlen($prefix));
        $file = $dir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require $base . '/core/Support/helpers.php';
