<?php

use App\Controllers\AuthController;
use Slim\App;

return function (App $app) {
    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/admin/login', [AuthController::class, 'login']);
    $app->post('/api/admin/logout', [AuthController::class, 'logout']);
};
