<?php

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app) {
    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/admin/login', [AuthController::class, 'login']);
    $app->post('/api/admin/logout', [AuthController::class, 'logout']);

    $app->group('/api', function ($group) {
        $group->get('/users', [UserController::class, 'index']);
        $group->post('/users', [UserController::class, 'create']);
        $group->get('/users/{id}', [UserController::class, 'show']);
        $group->put('/users/{id}', [UserController::class, 'update']);
        $group->delete('/users/{id}', [UserController::class, 'delete']);
    })->add(new AuthMiddleware());
};
