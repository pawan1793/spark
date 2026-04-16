<?php

namespace Spark\Config;

class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        // Guard against world-readable .env files on POSIX systems — secrets
        // inside must not be visible to other OS users. Only warn in the
        // error log; we don't abort because CI and containers often run as
        // root with default perms.
        if (function_exists('fileperms') && DIRECTORY_SEPARATOR === '/') {
            $perms = @fileperms($path);
            if ($perms !== false && ($perms & 0o004)) {
                error_log(".env file $path is world-readable. chmod 600 recommended.");
            }
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            // Only accept keys that look like environment variable names.
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }
            $value = self::normalize($value);

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return $value;
    }

    private static function normalize(string $value): string
    {
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }
        return $value;
    }
}
