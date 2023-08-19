<?php

declare (strict_types = 1);

require __DIR__ . '/../Autoload.php';
Autoload::register();

use App\App;
use App\Container;
use App\Http\Router;

$app = new App(rootDir: dirname(__DIR__));
$container = new Container();
$router = new Router();

$router->get(
    route: "/example",
    onMatch: fn($request) => $container->getExampleController()->doStuff($request),
);

http_response_code(404);
