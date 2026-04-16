<?php

namespace Spark\Console\Commands;

use Spark\Console\Command;

class MakeMiddlewareCommand extends Command
{
    public string $description = 'Create a new middleware class.';

    public function handle(array $args, array $options): int
    {
        if (empty($args[0])) {
            $this->error('Usage: php spark make:middleware <Name>');
            return 1;
        }

        $name = $args[0];
        $path = $this->app->basePath . "/app/Middleware/$name.php";

        $contents = <<<PHP
<?php

namespace App\Middleware;

use Closure;
use Spark\Http\Request;
use Spark\Http\Response;

class $name
{
    public function handle(Request \$request, Closure \$next): Response
    {
        // Run code before the request is handled ...
        \$response = \$next(\$request);
        // Or after ...
        return \$response;
    }
}
PHP;

        if ($this->writeFile($path, $contents)) {
            $this->info("Middleware created: app/Middleware/$name.php");
            return 0;
        }
        return 1;
    }
}
