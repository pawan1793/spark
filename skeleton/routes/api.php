<?php

/** @var \Spark\Router\Router $router */

use Spark\Http\Request;

$router->get('/status', function (Request $request) {
    return ['status' => 'ok', 'timestamp' => time()];
});
