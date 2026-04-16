<?php

namespace Spark\Support;

use Spark\Application;
use Throwable;

class ErrorHandler
{
    protected static bool $debug = false;
    protected static ?Application $app = null;

    public static function register(bool $debug, Application $app): void
    {
        self::$debug = $debug;
        self::$app = $app;

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $level)) {
            return false;
        }
        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        self::logException($e);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\n\033[31m" . get_class($e) . ': ' . $e->getMessage() . "\033[0m\n");
            fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . "\n");
            if (self::$debug) {
                fwrite(STDERR, $e->getTraceAsString() . "\n");
            }
            exit(1);
        }

        $status = $e instanceof \Spark\Http\HttpException ? $e->statusCode : 500;

        if (!headers_sent()) {
            http_response_code($status);
        }

        $wantsJson = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

        if (!headers_sent()) {
            // Apply minimal security headers even on error pages so an
            // exception page can't be framed / MIME-sniffed into an attack.
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }

        if ($wantsJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            $payload = [
                'status' => $status,
                'message' => $e instanceof \Spark\Http\HttpException
                    ? $e->getMessage()
                    : (self::$debug ? $e->getMessage() : self::genericMessage($status)),
            ];
            if (self::$debug) {
                $payload['error'] = get_class($e);
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
                $payload['trace'] = explode("\n", $e->getTraceAsString());
            }
            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        echo self::renderHtml($e, $status);
    }

    protected static function genericMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            default => 'Internal Server Error',
        };
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleException(new \ErrorException(
                $err['message'], 0, $err['type'], $err['file'], $err['line']
            ));
        }
    }

    protected static function logException(Throwable $e): void
    {
        if (!self::$app) return;
        try {
            $logger = self::$app->make(Logger::class);
            $logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (Throwable) {
            // swallow — logging must not cascade
        }
    }

    protected static function renderHtml(Throwable $e, int $status = 500): string
    {
        if (!self::$debug) {
            $label = $e instanceof \Spark\Http\HttpException
                ? $e->getMessage()
                : self::genericMessage($status);
            $label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $statusSafe = (int) $status;
            return "<!doctype html><html><head><title>$statusSafe</title><style>body{font-family:system-ui;padding:4rem;text-align:center;color:#444}h1{font-size:4rem;margin:0}</style></head><body><h1>$statusSafe</h1><p>$label</p></body></html>";
        }

        $class = htmlspecialchars(get_class($e));
        $msg = htmlspecialchars($e->getMessage());
        $file = htmlspecialchars($e->getFile());
        $line = $e->getLine();
        $trace = htmlspecialchars($e->getTraceAsString());

        return <<<HTML
<!doctype html>
<html><head><title>$class</title>
<style>
body{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#1a1a1a;color:#eee;margin:0;padding:2rem}
.h{background:#c0392b;padding:1.5rem;border-radius:8px;margin-bottom:1rem}
.h h1{margin:0;font-size:1.2rem}
.h p{margin:.5rem 0 0;opacity:.9}
.box{background:#2a2a2a;padding:1rem;border-radius:6px;margin-bottom:1rem}
pre{margin:0;white-space:pre-wrap;word-break:break-word;font-size:.85rem;line-height:1.5}
.loc{color:#f1c40f}
</style></head>
<body>
<div class="h"><h1>$class</h1><p>$msg</p></div>
<div class="box"><p class="loc">$file:$line</p></div>
<div class="box"><pre>$trace</pre></div>
</body></html>
HTML;
    }
}
