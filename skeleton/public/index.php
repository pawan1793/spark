<?php

use Spark\Application;

define('SPARK_START', microtime(true));

require __DIR__ . '/../bootstrap/autoload.php';

$app = new Application(dirname(__DIR__));
$app->run();
