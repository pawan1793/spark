<?php

namespace Spark\Http;

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected string $content = '';

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function headers(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
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
        $this->headers['Content-Type'] = 'application/json';
        $this->content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

    public function redirect(string $url, int $status = 302): self
    {
        $this->status = $status;
        $this->headers['Location'] = $url;
        return $this;
    }

    public function getStatus(): int { return $this->status; }
    public function getContent(): string { return $this->content; }
    public function getHeaders(): array { return $this->headers; }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("$name: $value", true);
            }
        }
        echo $this->content;
    }
}
