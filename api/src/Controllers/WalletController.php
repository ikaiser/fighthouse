<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\WalletService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WalletController
{
    private WalletService $walletService;

    public function __construct(?WalletService $walletService = null)
    {
        $this->walletService = $walletService ?? new WalletService();
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($this->buildPayload($user));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function lookup(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = User::where('telefono', $data['telefono'] ?? '')->first();

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'nessun utente con questo numero']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($this->buildPayload($user));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function buildPayload(User $user): string
    {
        return json_encode([
            'nome' => $user->nome,
            'cognome' => $user->cognome,
            'wallet_balance' => $user->wallet_balance,
            'max_redeemable' => $this->walletService->maxRedeemable($user),
            'transactions' => $user->walletTransactions()->get()->map(fn ($t) => [
                'tipo' => $t->tipo,
                'importo' => $t->importo,
                'saldo_dopo' => $t->saldo_dopo,
                'created_at' => (string) $t->created_at,
            ]),
        ]);
    }
}
