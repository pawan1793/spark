<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;

class MakeModelCommand extends Command
{
    public string $description = 'Create a new model class.';

    public function handle(array $args, array $options): int
    {
        if (empty($args[0])) {
            $this->error('Usage: php spark make:model <Name>');
            return 1;
        }

        $name = $args[0];
        $path = $this->app->basePath . "/app/Models/$name.php";

        $contents = <<<PHP
<?php

namespace App\Models;

use Spark\Database\Model;

class $name extends Model
{
    protected static array \$fillable = [];
}
PHP;

        if ($this->writeFile($path, $contents)) {
            $this->info("Model created: app/Models/$name.php");
            return 0;
        }
        return 1;
    }
}
