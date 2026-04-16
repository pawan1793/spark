<?php

namespace Spark\Console;

use Spark\Application;

abstract class Command
{
    public string $signature = '';
    public string $description = '';

    public function __construct(protected Application $app) {}

    abstract public function handle(array $args, array $options): int;

    public function line(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
    public function info(string $msg): void { fwrite(STDOUT, "\033[32m$msg\033[0m" . PHP_EOL); }
    public function warn(string $msg): void { fwrite(STDOUT, "\033[33m$msg\033[0m" . PHP_EOL); }
    public function error(string $msg): void { fwrite(STDERR, "\033[31m$msg\033[0m" . PHP_EOL); }

    protected function stub(string $name): string
    {
        $path = __DIR__ . "/stubs/$name.stub";
        if (!is_file($path)) {
            throw new \RuntimeException("Stub [$name] not found.");
        }
        return file_get_contents($path);
    }

    protected function writeFile(string $path, string $contents, bool $force = false): bool
    {
        if (is_file($path) && !$force) {
            $this->error("File already exists: $path");
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($path, $contents);
        return true;
    }
}
