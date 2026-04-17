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

            // Auto-detect external origins from the rendered HTML
            $detected = $this->detectCspOrigins($this->content);

            // Merge with optional config/csp.php overrides (config wins on conflicts)
            $config = function_exists('config') ? (array) config('csp', []) : [];
            $directives = ['script_src', 'style_src', 'img_src', 'font_src', 'connect_src', 'frame_ancestors', 'default_src'];
            $merged = [];
            foreach ($directives as $key) {
                $merged[$key] = array_values(array_unique(array_merge(
                    $detected[$key] ?? [],
                    array_map('trim', (array) ($config[$key] ?? [])),
                )));
            }

            // 'unsafe-inline' is silently ignored by browsers when a nonce is present (CSP spec §2.4.1)
            if ($hasNonce) {
                foreach (['script_src', 'style_src'] as $key) {
                    $merged[$key] = array_values(array_filter(
                        $merged[$key],
                        fn($s) => strtolower($s) !== "'unsafe-inline'"
                    ));
                }
            }

            // Inline style="..." attributes need 'unsafe-hashes' + per-value SHA-256 hashes.
            // Nonces and 'unsafe-inline' do not cover style attributes.
            $inlineStyleHashes = array_values(array_unique(array_merge(
                $detected['inline_style_hashes'] ?? [],
                array_map('trim', (array) ($config['inline_style_hashes'] ?? [])),
            )));

            $src = function (string $key) use ($merged): string {
                $parts = array_filter($merged[$key]);
                return $parts ? ' ' . implode(' ', $parts) : '';
            };

            $styleInlineDirective = $inlineStyleHashes
                ? " 'unsafe-hashes' " . implode(' ', $inlineStyleHashes)
                : '';

            $this->headers['Content-Security-Policy'] =
                "default-src 'self'" . $src('default_src') . "; "
                . "script-src 'self'$nonceSrc" . $src('script_src') . "; "
                . "style-src 'self'$nonceSrc" . $src('style_src') . $styleInlineDirective . "; "
                . "img-src 'self' data:" . $src('img_src') . "; "
                . "font-src 'self' data:" . $src('font_src') . "; "
                . "connect-src 'self'" . $src('connect_src') . "; "
                . "object-src 'none'; "
                . "base-uri 'self'; "
                . "frame-ancestors 'self'" . $src('frame_ancestors');
        }
    }

    protected function detectCspOrigins(string $html): array
    {
        $origins = ['script_src' => [], 'style_src' => [], 'img_src' => [], 'font_src' => [], 'connect_src' => [], 'inline_style_hashes' => []];

        // <script src="https://...">
        preg_match_all('/<script[^>]+\bsrc=["\']?(https?:\/\/[^"\'>\s]+)/i', $html, $m);
        foreach ($m[1] as $url) {
            if ($o = $this->urlOrigin($url)) $origins['script_src'][] = $o;
        }

        // <img src="..."> and <source src="...">
        preg_match_all('/<(?:img|source)[^>]+\bsrc=["\']?(https?:\/\/[^"\'>\s]+)/i', $html, $m);
        foreach ($m[1] as $url) {
            if ($o = $this->urlOrigin($url)) $origins['img_src'][] = $o;
        }

        // <link> — map rel/as to the right directive
        preg_match_all('/<link([^>]+)>/i', $html, $links);
        foreach ($links[1] as $attrs) {
            if (!preg_match('/\bhref=["\']?(https?:\/\/[^"\'>\s]+)/i', $attrs, $href)) continue;
            $rel = '';
            if (preg_match('/\brel=["\']([^"\']+)["\']/i', $attrs, $rm)) $rel = strtolower(trim($rm[1]));
            $as  = '';
            if (preg_match('/\bas=["\']([^"\']+)["\']/i',  $attrs, $am)) $as  = strtolower(trim($am[1]));

            if ($rel === 'stylesheet') {
                if ($o = $this->urlOrigin($href[1])) $origins['style_src'][] = $o;
            } elseif ($rel === 'preload' && $as === 'font') {
                if ($o = $this->urlOrigin($href[1])) $origins['font_src'][] = $o;
            } elseif (in_array($rel, ['icon', 'shortcut icon', 'apple-touch-icon'])) {
                if ($o = $this->urlOrigin($href[1])) $origins['img_src'][] = $o;
            }
        }

        // Browsers fetch .map source files from the same CDN hosts — allow them in connect-src
        $origins['connect_src'] = array_values(array_unique(array_merge(
            $origins['connect_src'],
            $origins['script_src'],
            $origins['style_src'],
        )));

        // Inline style="..." attributes — collect SHA-256 hashes for 'unsafe-hashes' support.
        // Strip PHP echo tags before hashing so we hash the literal CSS text, not the PHP source.
        $stripped = preg_replace('/<\?php.*?\?>/s', '', $html);
        preg_match_all('/\bstyle=["\']([^"\']+)["\']/i', $stripped, $m);
        foreach ($m[1] as $styleValue) {
            $hash = base64_encode(hash('sha256', $styleValue, true));
            $origins['inline_style_hashes'][] = "'sha256-$hash'";
        }

        return array_map(fn($list) => array_values(array_unique($list)), $origins);
    }

    private function urlOrigin(string $url): string
    {
        $p = parse_url($url);
        return ($p && !empty($p['scheme']) && !empty($p['host']))
            ? $p['scheme'] . '://' . $p['host']
            : '';
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
