<?php

namespace Spark\Http;

use InvalidArgumentException;

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected string $content = '';

    /**
     * Default security headers applied to every response unless explicitly
     * overridden. Callers can disable individual headers by passing `null`.
     */
    protected static array $defaultSecurityHeaders = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-XSS-Protection' => '0',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ];

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        if (!self::isSafeHeaderValue($name) || !self::isSafeHeaderValue($value)) {
            throw new InvalidArgumentException('Header name or value contains CR/LF.');
        }
        $this->headers[$name] = $value;
        return $this;
    }

    public function headers(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->header($k, (string) $v);
        }
        return $this;
    }

    public function body(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function json(mixed $data, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'application/json; charset=UTF-8';
        $this->content = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        return $this;
    }

    public function html(string $html, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'text/html; charset=UTF-8';
        $this->content = $html;
        return $this;
    }

    public function text(string $text, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'text/plain; charset=UTF-8';
        $this->content = $text;
        return $this;
    }

    /**
     * Redirect to the given URL. By default only same-origin and relative URLs
     * are accepted — this prevents open-redirect attacks where user-supplied
     * URLs would otherwise be forwarded verbatim. Pass $allowExternal=true
     * only when the target is a trusted, statically defined URL.
     */
    public function redirect(string $url, int $status = 302, bool $allowExternal = false): self
    {
        $url = self::sanitizeRedirectTarget($url, $allowExternal);
        $this->status = $status;
        $this->headers['Location'] = $url;
        return $this;
    }

    public function getStatus(): int { return $this->status; }
    public function getContent(): string { return $this->content; }
    public function getHeaders(): array { return $this->headers; }

    public function send(): void
    {
        $this->applyDefaultSecurityHeaders();

        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("$name: $value", true);
            }
        }
        echo $this->content;
    }

    protected function applyDefaultSecurityHeaders(): void
    {
        foreach (self::$defaultSecurityHeaders as $name => $value) {
            if (!isset($this->headers[$name])) {
                $this->headers[$name] = $value;
            }
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

        if ($isHttps && !isset($this->headers['Strict-Transport-Security'])) {
            $this->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (!isset($this->headers['Content-Security-Policy'])
            && isset($this->headers['Content-Type'])
            && str_starts_with($this->headers['Content-Type'], 'text/html')) {
            $nonce    = function_exists('csp_nonce') ? csp_nonce() : '';
            $hasNonce = $nonce !== '';
            $nonceSrc = $hasNonce ? " 'nonce-$nonce'" : '';
            $extra    = function_exists('config') ? (array) config('csp', []) : [];

            // 'unsafe-inline' is silently ignored by browsers when a nonce is present (CSP spec §2.4.1)
            if ($hasNonce) {
                foreach (['script_src', 'style_src'] as $key) {
                    if (isset($extra[$key])) {
                        $extra[$key] = array_filter(
                            (array) $extra[$key],
                            fn($s) => strtolower(trim($s)) !== "'unsafe-inline'"
                        );
                    }
                }
            }

            $src = function (string $key) use ($extra): string {
                if (empty($extra[$key])) {
                    return '';
                }
                $parts = array_filter(array_map('trim', (array) $extra[$key]));
                return $parts ? ' ' . implode(' ', $parts) : '';
            };

            $this->headers['Content-Security-Policy'] =
                "default-src 'self'" . $src('default_src') . "; "
                . "script-src 'self'$nonceSrc" . $src('script_src') . "; "
                . "style-src 'self'$nonceSrc" . $src('style_src') . "; "
                . "img-src 'self' data:" . $src('img_src') . "; "
                . "font-src 'self' data:" . $src('font_src') . "; "
                . "connect-src 'self'" . $src('connect_src') . "; "
                . "object-src 'none'; "
                . "base-uri 'self'; "
                . "frame-ancestors 'self'" . $src('frame_ancestors');
        }
    }

    /**
     * Validates and normalizes a redirect target. Allows:
     *   - Relative paths beginning with "/" but not "//" (the latter is
     *     protocol-relative and can send users to any host).
     *   - Same-origin absolute URLs.
     *   - Any URL when $allowExternal is true.
     */
    protected static function sanitizeRedirectTarget(string $url, bool $allowExternal): string
    {
        $url = trim($url);

        if ($url === '' || !self::isSafeHeaderValue($url)) {
            throw new InvalidArgumentException('Invalid redirect URL.');
        }

        if ($allowExternal) {
            return $url;
        }

        // Reject protocol-relative URLs (//example.com) and backslash tricks.
        if (str_starts_with($url, '//') || str_starts_with($url, '\\\\') || str_contains($url, "\r") || str_contains($url, "\n")) {
            throw new InvalidArgumentException('External redirect not allowed.');
        }

        // Relative path — always safe.
        if (str_starts_with($url, '/')) {
            return $url;
        }

        // Absolute URL — only allow same-origin.
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['scheme'])) {
            // Not an absolute URL; relative to current path — allowed.
            return $url;
        }
        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidArgumentException('Invalid redirect scheme.');
        }
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        if (($parsed['host'] ?? '') !== $currentHost) {
            throw new InvalidArgumentException('External redirect not allowed.');
        }
        return $url;
    }

    protected static function isSafeHeaderValue(string $value): bool
    {
        return !preg_match('/[\r\n\0]/', $value);
    }
}
