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

    public function testFailedLoginIncrementsAttemptCounter(): void
    {
        $admin = Admin::create(['username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_BCRYPT)]);

        $controller = new AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
            ->withParsedBody(['username' => 'admin', 'password' => 'wrong']);
        $controller->login($request, new Response());

        $this->assertEquals(1, $admin->fresh()->failed_attempts);
    }

    public function testLoginLocksAccountAfterFiveFailedAttempts(): void
    {
        $admin = Admin::create(['username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_BCRYPT)]);

        $controller = new AuthController();
        for ($i = 0; $i < 5; $i++) {
            $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
                ->withParsedBody(['username' => 'admin', 'password' => 'wrong']);
            $controller->login($request, new Response());
        }

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
            ->withParsedBody(['username' => 'admin', 'password' => 'secret123']);
        $response = $controller->login($request, new Response());

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
    }

    public function testLoginSucceedsAfterLockoutExpires(): void
    {
        $admin = Admin::create([
            'username' => 'admin',
            'password_hash' => password_hash('secret123', PASSWORD_BCRYPT),
            'failed_attempts' => 5,
            'locked_until' => (new \DateTime('-1 minute'))->format('Y-m-d H:i:s'),
        ]);

        $controller = new AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
            ->withParsedBody(['username' => 'admin', 'password' => 'secret123']);
        $response = $controller->login($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(0, $admin->fresh()->failed_attempts);
        $this->assertNull($admin->fresh()->locked_until);
    }

    public function testSuccessfulLoginResetsFailedAttempts(): void
    {
        $admin = Admin::create([
            'username' => 'admin',
            'password_hash' => password_hash('secret123', PASSWORD_BCRYPT),
            'failed_attempts' => 3,
        ]);

        $controller = new AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/admin/login')
            ->withParsedBody(['username' => 'admin', 'password' => 'secret123']);
        $controller->login($request, new Response());

        $this->assertEquals(0, $admin->fresh()->failed_attempts);
    }

    public function testMeReturns200WhenSessionActive(): void
    {
        $admin = Admin::create(['username' => 'admin', 'password_hash' => password_hash('secret123', PASSWORD_BCRYPT)]);
        $_SESSION['admin_id'] = $admin->id;

        $controller = new AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/admin/me');
        $response = $controller->me($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMeReturns401WhenNoSession(): void
    {
        $controller = new AuthController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/admin/me');
        $response = $controller->me($request, new Response());

        $this->assertEquals(401, $response->getStatusCode());
    }
}
