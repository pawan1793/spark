<?php

namespace Spark\Http;

/**
 * Thin wrapper around PHP sessions with secure defaults.
 *
 * - HttpOnly, SameSite=Lax cookies so JS can't read session id and common
 *   CSRF vectors are blunted at the cookie layer.
 * - Secure flag toggled on when the current request is HTTPS.
 * - Session IDs are regenerated on demand (call regenerate() after login) to
 *   prevent session fixation.
 * - CSRF token is a 256-bit cryptographic random value, compared with
 *   hash_equals() to avoid timing attacks.
 */
class Session
{
    public const CSRF_KEY = '_csrf';

    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Harden PHP session handling.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if ($secure) {
            ini_set('session.cookie_secure', '1');
        }

        session_start();
    }

    public static function regenerate(bool $deleteOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $p['path'],
                    $p['domain'],
                    $p['secure'],
                    $p['httponly']
                );
            }
            session_destroy();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        self::start();
        $expected = $_SESSION[self::CSRF_KEY] ?? '';
        if (!is_string($token) || $token === '' || !is_string($expected) || $expected === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }
}
