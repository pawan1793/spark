<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;

class MakeMigrationCommand extends Command
{
    public string $description = 'Create a new migration file.';

    public function handle(array $args, array $options): int
    {
        if (empty($args[0])) {
            $this->error('Usage: php spark make:migration <name>');
            return 1;
        }

        $name = $args[0];
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_$name";
        $path = $this->app->basePath . "/database/migrations/$filename.php";

        $table = $this->guessTable($name);

        $contents = <<<PHP
<?php

use Spark\Database\Migration;
use Spark\Database\Schema;
use Spark\Database\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('$table', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('$table');
    }
};
PHP;

        if ($this->writeFile($path, $contents)) {
            $this->info("Migration created: database/migrations/$filename.php");
            return 0;
        }
        return 1;
    }

    protected function guessTable(string $name): string
    {
        if (preg_match('/create_(\w+)_table/', $name, $m)) {
            return $m[1];
        }
        return $name;
    }
}
