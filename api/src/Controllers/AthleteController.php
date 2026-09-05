<?php

namespace App\Controllers;

use App\Models\Athlete;
use App\Models\Coach;
use App\Support\Pagination;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AthleteController
{
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $search = $params['q'] ?? null;
        $query = Athlete::with('coach');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('cognome', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }
        $query->orderBy('cognome');

        if (!empty($params['page'])) {
            $response->getBody()->write(Pagination::toJson($query, $params));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($query->get()->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data);
        if ($errors) {
            $response->getBody()->write(json_encode(['error' => implode(', ', $errors)]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if ($this->phoneTaken($data['telefono'])) {
            $response->getBody()->write(json_encode(['error' => 'telefono già registrato']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $athlete = Athlete::create([
            'nome' => $data['nome'], 'cognome' => $data['cognome'], 'telefono' => $data['telefono'],
            'email' => $data['email'], 'data_nascita' => $data['data_nascita'], 'coach_id' => $data['coach_id'],
            'wallet_balance' => 0,
        ]);
        $athlete->load('coach');

        $response->getBody()->write($athlete->toJson());
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $athlete = Athlete::with('coach')->find($args['id']);
        if (!$athlete) {
            $response->getBody()->write(json_encode(['error' => 'atleta non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write($athlete->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $athlete = Athlete::find($args['id']);
        if (!$athlete) {
            $response->getBody()->write(json_encode(['error' => 'atleta non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data);
        if ($errors) {
            $response->getBody()->write(json_encode(['error' => implode(', ', $errors)]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if ($this->phoneTaken($data['telefono'], $athlete->id)) {
            $response->getBody()->write(json_encode(['error' => 'telefono già registrato']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $athlete->update([
            'nome' => $data['nome'], 'cognome' => $data['cognome'], 'telefono' => $data['telefono'],
            'email' => $data['email'], 'data_nascita' => $data['data_nascita'], 'coach_id' => $data['coach_id'],
        ]);
        $athlete->load('coach');

        $response->getBody()->write($athlete->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $athlete = Athlete::find($args['id']);
        if (!$athlete) {
            $response->getBody()->write(json_encode(['error' => 'atleta non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $athlete->delete();
        return $response->withStatus(204);
    }

    private function phoneTaken(string $telefono, ?int $excludeId = null): bool
    {
        $athleteQuery = Athlete::where('telefono', $telefono);
        if ($excludeId) {
            $athleteQuery->where('id', '!=', $excludeId);
        }

        return $athleteQuery->exists() || Coach::where('telefono', $telefono)->exists();
    }

    private function validate(array $data): array
    {
        $errors = [];
        foreach (['nome', 'cognome', 'telefono', 'email', 'data_nascita', 'coach_id'] as $field) {
            if (empty($data[$field])) {
                $errors[] = "{$field} obbligatorio";
            }
        }
        if (!empty($data['data_nascita']) && !\DateTime::createFromFormat('Y-m-d', $data['data_nascita'])) {
            $errors[] = 'data_nascita non valida';
        }
        if (!empty($data['coach_id']) && !Coach::find($data['coach_id'])) {
            $errors[] = 'coach non trovato';
        }
        return $errors;
    }
}
