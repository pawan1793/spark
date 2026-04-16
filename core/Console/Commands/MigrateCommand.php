<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;
use Spark\Database\Migrator;

class MigrateCommand extends Command
{
    public string $description = 'Run all pending database migrations.';

    public function handle(array $args, array $options): int
    {
        $migrator = new Migrator($this->app);
        $count = $migrator->run(fn($msg) => $this->line($msg));
        $this->info("Done. $count migration(s) run.");
        return 0;
    }
}
