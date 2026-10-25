<?php

use App\Controllers\AthleteController;
use App\Controllers\AuthController;
use App\Controllers\CoachController;
use App\Controllers\ExpenseController;
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
    $app->get('/api/admin/me', [AuthController::class, 'me']);
    $app->post('/api/wallet/lookup', [WalletController::class, 'lookup']);

    $app->group('/api', function ($group) {
        $group->get('/athletes', [AthleteController::class, 'index']);
        $group->post('/athletes', [AthleteController::class, 'create']);
        $group->get('/athletes/{id}', [AthleteController::class, 'show']);
        $group->put('/athletes/{id}', [AthleteController::class, 'update']);
        $group->delete('/athletes/{id}', [AthleteController::class, 'delete']);
        $group->get('/athletes/{id}/wallet', [WalletController::class, 'show']);

        $group->get('/coaches', [CoachController::class, 'index']);
        $group->post('/coaches', [CoachController::class, 'create']);
        $group->get('/coaches/{id}', [CoachController::class, 'show']);
        $group->put('/coaches/{id}', [CoachController::class, 'update']);
        $group->delete('/coaches/{id}', [CoachController::class, 'delete']);
        $group->get('/coaches/{id}/wallet', [WalletController::class, 'showCoach']);
        $group->post('/coaches/{id}/wallet/credit', [WalletController::class, 'creditCoach']);

        $group->get('/expenses', [ExpenseController::class, 'index']);
        $group->post('/expenses', [ExpenseController::class, 'create']);
        $group->put('/expenses/{id}', [ExpenseController::class, 'update']);
        $group->delete('/expenses/{id}', [ExpenseController::class, 'delete']);
    })->add(new AuthMiddleware());
};
