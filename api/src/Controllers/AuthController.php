<?php

namespace App\Controllers;

use App\Models\Admin;
use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $admin = Admin::where('username', $data['username'] ?? '')->first();

        if ($admin && $admin->locked_until && Carbon::parse($admin->locked_until)->isFuture()) {
            $response->getBody()->write(json_encode(['error' => 'troppi tentativi falliti, riprova più tardi']));
            return $response->withStatus(429)->withHeader('Content-Type', 'application/json');
        }

        if (!$admin || !password_verify((string) ($data['password'] ?? ''), $admin->password_hash)) {
            if ($admin) {
                $admin->failed_attempts++;
                if ($admin->failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
                    $admin->locked_until = Carbon::now()->addMinutes(self::LOCKOUT_MINUTES);
                }
                $admin->save();
            }
            $response->getBody()->write(json_encode(['error' => 'credenziali non valide']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $admin->failed_attempts = 0;
        $admin->locked_until = null;
        $admin->save();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
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

    public function me(Request $request, Response $response): Response
    {
        if (empty($_SESSION['admin_id'])) {
            $response->getBody()->write(json_encode(['authenticated' => false]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['authenticated' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
