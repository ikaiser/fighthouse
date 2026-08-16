<?php

namespace App\Controllers;

use App\Models\Expense;
use App\Models\User;
use App\Services\WalletService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ExpenseController
{
    private WalletService $walletService;

    public function __construct(?WalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new WalletService();
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = Expense::query()->orderByDesc('data_spesa');

        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['from'])) {
            $query->where('data_spesa', '>=', $params['from']);
        }
        if (!empty($params['to'])) {
            $query->where('data_spesa', '<=', $params['to']);
        }

        $response->getBody()->write($query->get()->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = User::find($data['user_id'] ?? null);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $expense = $this->walletService->createExpense(
                $user,
                (float) ($data['importo'] ?? 0),
                (string) ($data['data_spesa'] ?? ''),
                $data['note'] ?? null,
                (float) ($data['wallet_usato'] ?? 0)
            );
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($expense->toJson());
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $expense = Expense::find($args['id']);
        if (!$expense) {
            $response->getBody()->write(json_encode(['error' => 'spesa non trovata']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data = (array) $request->getParsedBody();

        try {
            $expense = $this->walletService->updateExpense(
                $expense,
                (float) ($data['importo'] ?? 0),
                (string) ($data['data_spesa'] ?? ''),
                $data['note'] ?? null,
                (float) ($data['wallet_usato'] ?? 0)
            );
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($expense->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $expense = Expense::find($args['id']);
        if (!$expense) {
            $response->getBody()->write(json_encode(['error' => 'spesa non trovata']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $this->walletService->deleteExpense($expense);
        return $response->withStatus(204);
    }
}
