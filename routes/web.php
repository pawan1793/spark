<?php

/** @var \Spark\Router\Router $router */

use App\Controllers\HomeController;
use Spark\Http\Request;

$router->get('/', [HomeController::class, 'index']);
$router->get('/hello/{name}', [HomeController::class, 'hello']);

$router->get('/ping', function (Request $request) {
    return ['pong' => true, 'ip' => $request->ip()];
});
