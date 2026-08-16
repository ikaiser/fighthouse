<?php

namespace Tests\Controllers;

use App\Controllers\UserController;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\TestDatabase;

class UserControllerTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabase::boot();
        TestDatabase::migrate();
    }

    protected function tearDown(): void
    {
        TestDatabase::reset();
    }

    public function testCreateUserPersistsAllFields(): void
    {
        $controller = new UserController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/users')
            ->withParsedBody([
                'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990000',
                'email' => 'anna@example.com', 'data_nascita' => '1995-05-20',
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(1, User::count());
    }

    public function testCreateUserRejectsDuplicatePhone(): void
    {
        User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '3339990000',
            'email' => 'a@example.com', 'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);

        $controller = new UserController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/users')
            ->withParsedBody([
                'nome' => 'C', 'cognome' => 'D', 'telefono' => '3339990000',
                'email' => 'c@example.com', 'data_nascita' => '1990-01-01',
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testUpdateUserChangesFields(): void
    {
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '3330000001',
            'email' => 'a@example.com', 'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);

        $controller = new UserController();
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/api/users/{$user->id}")
            ->withParsedBody([
                'nome' => 'Aggiornato', 'cognome' => 'B', 'telefono' => '3330000001',
                'email' => 'a@example.com', 'data_nascita' => '1990-01-01',
            ]);
        $response = $controller->update($request, new Response(), ['id' => $user->id]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Aggiornato', $user->fresh()->nome);
    }

    public function testDeleteUserRemovesRow(): void
    {
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '3330000002',
            'email' => 'a@example.com', 'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);

        $controller = new UserController();
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/users/{$user->id}");
        $response = $controller->delete($request, new Response(), ['id' => $user->id]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull(User::find($user->id));
    }

    public function testShowReturns404ForUnknownUser(): void
    {
        $controller = new UserController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/users/999');
        $response = $controller->show($request, new Response(), ['id' => 999]);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
