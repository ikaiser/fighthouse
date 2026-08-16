<?php

use App\Controllers\AuthController;
use App\Controllers\ExpenseController;
use App\Controllers\UserController;
use App\Controllers\WalletController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app) {
    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/admin/login', [AuthController::class, 'login']);
    $app->post('/api/admin/logout', [AuthController::class, 'logout']);
    $app->post('/api/wallet/lookup', [WalletController::class, 'lookup']);

    $app->group('/api', function ($group) {
        $group->get('/users', [UserController::class, 'index']);
        $group->post('/users', [UserController::class, 'create']);
        $group->get('/users/{id}', [UserController::class, 'show']);
        $group->put('/users/{id}', [UserController::class, 'update']);
        $group->delete('/users/{id}', [UserController::class, 'delete']);
        $group->get('/users/{id}/wallet', [WalletController::class, 'show']);

        $group->get('/expenses', [ExpenseController::class, 'index']);
        $group->post('/expenses', [ExpenseController::class, 'create']);
        $group->put('/expenses/{id}', [ExpenseController::class, 'update']);
        $group->delete('/expenses/{id}', [ExpenseController::class, 'delete']);
    })->add(new AuthMiddleware());
};
