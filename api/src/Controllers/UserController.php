<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    public function index(Request $request, Response $response): Response
    {
        $search = $request->getQueryParams()['q'] ?? null;
        $query = User::query();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('cognome', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }
        $response->getBody()->write($query->orderBy('cognome')->get()->toJson());
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

        if (User::where('telefono', $data['telefono'])->exists()) {
            $response->getBody()->write(json_encode(['error' => 'telefono già registrato']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $user = User::create([
            'nome' => $data['nome'], 'cognome' => $data['cognome'], 'telefono' => $data['telefono'],
            'email' => $data['email'], 'data_nascita' => $data['data_nascita'], 'wallet_balance' => 0,
        ]);

        $response->getBody()->write($user->toJson());
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write($user->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->validate($data);
        if ($errors) {
            $response->getBody()->write(json_encode(['error' => implode(', ', $errors)]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (User::where('telefono', $data['telefono'])->where('id', '!=', $user->id)->exists()) {
            $response->getBody()->write(json_encode(['error' => 'telefono già registrato']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $user->update([
            'nome' => $data['nome'], 'cognome' => $data['cognome'], 'telefono' => $data['telefono'],
            'email' => $data['email'], 'data_nascita' => $data['data_nascita'],
        ]);

        $response->getBody()->write($user->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'utente non trovato']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $user->delete();
        return $response->withStatus(204);
    }

    private function validate(array $data): array
    {
        $errors = [];
        foreach (['nome', 'cognome', 'telefono', 'email', 'data_nascita'] as $field) {
            if (empty($data[$field])) {
                $errors[] = "{$field} obbligatorio";
            }
        }
        if (!empty($data['data_nascita']) && !\DateTime::createFromFormat('Y-m-d', $data['data_nascita'])) {
            $errors[] = 'data_nascita non valida';
        }
        return $errors;
    }
}
