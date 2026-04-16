<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;
use Spark\Database\Migrator;

class MigrateRollbackCommand extends Command
{
    public string $description = 'Rollback the last batch of migrations.';

    public function handle(array $args, array $options): int
    {
        $migrator = new Migrator($this->app);
        $count = $migrator->rollback(fn($msg) => $this->line($msg));
        $this->info("Done. $count migration(s) rolled back.");
        return 0;
    }
}
