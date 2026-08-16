<?php

namespace App;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\Factory\AppFactory;

function bootstrapApp(): \Slim\App
{
    $root = dirname(__DIR__);

    if (file_exists($root . '/.env')) {
        Dotenv::createImmutable($root)->load();
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'fighthouse',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $app = AppFactory::create();
    $app->addBodyParsingMiddleware();
    $app->addErrorMiddleware((bool) (($_ENV['APP_ENV'] ?? 'local') === 'local'), true, true);

    $registerRoutes = require __DIR__ . '/routes.php';
    $registerRoutes($app);

    return $app;
}
