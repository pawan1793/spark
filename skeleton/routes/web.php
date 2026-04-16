<?php

/** @var \Spark\Router\Router $router */

use App\Controllers\HomeController;

$router->get('/', [HomeController::class, 'index']);
