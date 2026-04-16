<?php

namespace Spark\Support;

class Logger
{
    protected const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    public function __construct(
        protected string $path,
        protected string $minLevel = 'debug'
    ) {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public function debug(string $message, array $context = []): void { $this->log('debug', $message, $context); }
    public function info(string $message, array $context = []): void { $this->log('info', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->log('warning', $message, $context); }
    public function error(string $message, array $context = []): void { $this->log('error', $message, $context); }

    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 0)) {
            return;
        }

        $time = date('Y-m-d H:i:s');
        $ctx = $context ? ' ' . json_encode($context) : '';
        $line = "[$time] " . strtoupper($level) . ": $message$ctx" . PHP_EOL;
        @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
