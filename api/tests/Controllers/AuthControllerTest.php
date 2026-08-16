<?php

namespace Tests\Controllers;

use App\Controllers\AuthController;
use App\Models\Admin;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\TestDatabase;

class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabase::boot();
        TestDatabase::migrate();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        TestDatabase::reset();
        $_SESSION = [];
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        Admin::create(['username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_BCRYPT)]);

        $controller = new AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
            ->withParsedBody(['username' => 'admin', 'password' => 'secret123']);
        $response = $controller->login($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('admin', Admin::find($_SESSION['admin_id'])->username);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        Admin::create(['username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_BCRYPT)]);

        $controller = new AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
            ->withParsedBody(['username' => 'admin', 'password' => 'wrong']);
        $response = $controller->login($request, new Response());

        $this->assertEquals(401, $response->getStatusCode());
    }
}
