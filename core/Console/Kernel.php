<?php

namespace Spark\Console;

use Spark\Application;
use Spark\Console\Commands\ServeCommand;
use Spark\Console\Commands\MakeControllerCommand;
use Spark\Console\Commands\MakeModelCommand;
use Spark\Console\Commands\MakeMiddlewareCommand;
use Spark\Console\Commands\MakeMigrationCommand;
use Spark\Console\Commands\MigrateCommand;
use Spark\Console\Commands\MigrateRollbackCommand;
use Spark\Console\Commands\RouteListCommand;
use Spark\Console\Commands\KeyGenerateCommand;

class Kernel
{
    /** @var array<string,string> signature => class */
    protected array $commands = [
        'serve' => ServeCommand::class,
        'make:controller' => MakeControllerCommand::class,
        'make:model' => MakeModelCommand::class,
        'make:middleware' => MakeMiddlewareCommand::class,
        'make:migration' => MakeMigrationCommand::class,
        'migrate' => MigrateCommand::class,
        'migrate:rollback' => MigrateRollbackCommand::class,
        'route:list' => RouteListCommand::class,
        'key:generate' => KeyGenerateCommand::class,
    ];

    public function __construct(protected Application $app) {}

    public function handle(array $argv): int
    {
        array_shift($argv); // script name

        if (!$argv || in_array($argv[0], ['-h', '--help', 'help', 'list'])) {
            $this->printHelp();
            return 0;
        }

        $name = array_shift($argv);
        if (!isset($this->commands[$name])) {
            fwrite(STDERR, "Unknown command: $name\n\n");
            $this->printHelp();
            return 1;
        }

        [$args, $options] = $this->parseArgs($argv);

        /** @var Command $command */
        $command = $this->app->make($this->commands[$name]);
        return $command->handle($args, $options);
    }

    protected function parseArgs(array $argv): array
    {
        $args = [];
        $options = [];
        foreach ($argv as $token) {
            if (str_starts_with($token, '--')) {
                $pair = substr($token, 2);
                if (str_contains($pair, '=')) {
                    [$k, $v] = explode('=', $pair, 2);
                    $options[$k] = $v;
                } else {
                    $options[$pair] = true;
                }
            } else {
                $args[] = $token;
            }
        }
        return [$args, $options];
    }

    protected function printHelp(): void
    {
        $banner = <<<TXT
\033[36mSpark\033[0m — lightweight PHP framework CLI

Usage: php spark <command> [options]

Commands:
TXT;
        fwrite(STDOUT, $banner . PHP_EOL);

        foreach ($this->commands as $name => $class) {
            $instance = new $class($this->app);
            $desc = $instance->description ?: '';
            fwrite(STDOUT, sprintf("  \033[32m%-22s\033[0m %s\n", $name, $desc));
        }
        fwrite(STDOUT, PHP_EOL);
    }
}
