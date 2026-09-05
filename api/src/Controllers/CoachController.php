<?php

namespace App\Controllers;

use App\Models\Athlete;
use App\Models\Coach;
use App\Support\Pagination;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CoachController
{
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = Coach::orderBy('cognome');

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

        $coach = Coach::create([
            'nome' => $data['nome'], 'cognome' => $data['cognome'], 'telefono' => $data['telefono'],
            'email' => $data['email'], 'wallet_balance' => 0,
        ]);

        $response->getBody()->write($coach->toJson());
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $coach = Coach::find($args['id']);
        if (!$coach) {
            $response->getBody()->write(json_encode(['error' => 'coach non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write($coach->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $coach = Coach::find($args['id']);
        if (!$coach) {
            $response->getBody()->write(json_encode(['error' => 'coach non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data);
        if ($errors) {
            $response->getBody()->write(json_encode(['error' => implode(', ', $errors)]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if ($this->phoneTaken($data['telefono'], $coach->id)) {
            $response->getBody()->write(json_encode(['error' => 'telefono già registrato']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $coach->update([
            'nome' => $data['nome'], 'cognome' => $data['cognome'], 'telefono' => $data['telefono'],
            'email' => $data['email'],
        ]);

        $response->getBody()->write($coach->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $coach = Coach::find($args['id']);
        if (!$coach) {
            $response->getBody()->write(json_encode(['error' => 'coach non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if (Athlete::where('coach_id', $coach->id)->exists()) {
            $response->getBody()->write(json_encode(['error' => 'il coach ha ancora atleti assegnati']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $coach->delete();
        return $response->withStatus(204);
    }

    private function phoneTaken(string $telefono, ?int $excludeId = null): bool
    {
        $coachQuery = Coach::where('telefono', $telefono);
        if ($excludeId) {
            $coachQuery->where('id', '!=', $excludeId);
        }

        return $coachQuery->exists() || Athlete::where('telefono', $telefono)->exists();
    }

    private function validate(array $data): array
    {
        $errors = [];
        foreach (['nome', 'cognome', 'telefono', 'email'] as $field) {
            if (empty($data[$field])) {
                $errors[] = "{$field} obbligatorio";
            }
        }
        return $errors;
    }
}
