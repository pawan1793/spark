<?php

$base = dirname(__DIR__);

if (is_file($base . '/vendor/autoload.php')) {
    require $base . '/vendor/autoload.php';
    return;
}

// Fallback PSR-4 loader (without Composer)
spl_autoload_register(function (string $class) use ($base) {
    $prefixes = [
        'App\\' => $base . '/app/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
