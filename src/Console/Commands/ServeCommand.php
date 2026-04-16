<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;

class ServeCommand extends Command
{
    public string $description = 'Start the PHP built-in development server.';

    public function handle(array $args, array $options): int
    {
        $host = $options['host'] ?? '127.0.0.1';
        $port = (int) ($options['port'] ?? 8000);
        $docroot = $this->app->basePath . '/public';

        $this->info("Spark dev server running at http://$host:$port");
        $this->line("Press Ctrl+C to stop.");

        $cmd = sprintf(
            '%s -S %s:%d -t %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($host),
            $port,
            escapeshellarg($docroot)
        );
        passthru($cmd, $exit);
        return (int) $exit;
    }
}
