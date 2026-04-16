<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;

class MakeControllerCommand extends Command
{
    public string $description = 'Create a new controller class.';

    public function handle(array $args, array $options): int
    {
        if (empty($args[0])) {
            $this->error('Usage: php spark make:controller <Name>');
            return 1;
        }

        $name = $args[0];
        $path = $this->app->basePath . "/app/Controllers/$name.php";

        $contents = <<<PHP
<?php

namespace App\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;

class $name
{
    public function index(Request \$request): Response
    {
        return json(['message' => '$name works']);
    }
}
PHP;

        if ($this->writeFile($path, $contents)) {
            $this->info("Controller created: app/Controllers/$name.php");
            return 0;
        }
        return 1;
    }
}
