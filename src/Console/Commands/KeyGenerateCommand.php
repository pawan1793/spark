<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;

class KeyGenerateCommand extends Command
{
    public string $description = 'Generate a random APP_KEY in the .env file.';

    public function handle(array $args, array $options): int
    {
        $envFile = $this->app->basePath . '/.env';
        if (!is_file($envFile)) {
            $this->error('.env file not found. Copy .env.example to .env first.');
            return 1;
        }

        $key = 'base64:' . base64_encode(random_bytes(32));
        $contents = file_get_contents($envFile);

        if (preg_match('/^APP_KEY=.*$/m', $contents)) {
            $contents = preg_replace('/^APP_KEY=.*$/m', "APP_KEY=$key", $contents);
        } else {
            $contents .= PHP_EOL . "APP_KEY=$key" . PHP_EOL;
        }

        file_put_contents($envFile, $contents);
        $this->info("APP_KEY set successfully.");
        return 0;
    }
}
