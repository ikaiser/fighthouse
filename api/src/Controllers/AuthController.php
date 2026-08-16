<?php

namespace App\Controllers;

use App\Models\Admin;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $admin = Admin::where('username', $data['username'] ?? '')->first();

        if (!$admin || !password_verify((string) ($data['password'] ?? ''), $admin->password_hash)) {
            $response->getBody()->write(json_encode(['error' => 'credenziali non valide']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $_SESSION['admin_id'] = $admin->id;
        $response->getBody()->write(json_encode(['ok' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logout(Request $request, Response $response): Response
    {
        unset($_SESSION['admin_id']);
        $response->getBody()->write(json_encode(['ok' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
