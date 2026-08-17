<?php

namespace App\Controllers;

use App\Models\Athlete;
use App\Models\Coach;
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
        $athlete = Athlete::find($args['id']);
        if (!$athlete) {
            $response->getBody()->write(json_encode(['error' => 'atleta non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($this->buildAthletePayload($athlete));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function showCoach(Request $request, Response $response, array $args): Response
    {
        $coach = Coach::find($args['id']);
        if (!$coach) {
            $response->getBody()->write(json_encode(['error' => 'coach non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($this->buildCoachPayload($coach));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function lookup(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $telefono = $data['telefono'] ?? '';

        $athlete = Athlete::where('telefono', $telefono)->first();
        if ($athlete) {
            $response->getBody()->write($this->buildAthletePayload($athlete));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $coach = Coach::where('telefono', $telefono)->first();
        if ($coach) {
            $response->getBody()->write($this->buildCoachPayload($coach));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'nessun utente con questo numero']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    private function buildAthletePayload(Athlete $athlete): string
    {
        return json_encode([
            'type' => 'athlete',
            'nome' => $athlete->nome,
            'cognome' => $athlete->cognome,
            'wallet_balance' => $athlete->wallet_balance,
            'max_redeemable' => $this->walletService->maxRedeemable($athlete),
            'transactions' => $athlete->walletTransactions()->get()->map(fn ($t) => [
                'tipo' => $t->tipo,
                'importo' => $t->importo,
                'saldo_dopo' => $t->saldo_dopo,
                'created_at' => (string) $t->created_at,
            ]),
        ]);
    }

    private function buildCoachPayload(Coach $coach): string
    {
        return json_encode([
            'type' => 'coach',
            'nome' => $coach->nome,
            'cognome' => $coach->cognome,
            'wallet_balance' => $coach->wallet_balance,
            'transactions' => $coach->walletTransactions()->get()->map(fn ($t) => [
                'tipo' => $t->tipo,
                'importo' => $t->importo,
                'saldo_dopo' => $t->saldo_dopo,
                'created_at' => (string) $t->created_at,
            ]),
        ]);
    }
}
