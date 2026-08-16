<?php

namespace Tests\Controllers;

use App\Controllers\WalletController;
use App\Models\User;
use App\Services\WalletService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\TestDatabase;

class WalletControllerTest extends TestCase
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

    public function testLookupByPhoneReturnsBalanceAndHistory(): void
    {
        $user = User::create([
            'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990000',
            'email' => 'anna@example.com', 'data_nascita' => '1995-05-20', 'wallet_balance' => 0,
        ]);
        (new WalletService())->createExpense($user, 100, '2026-08-16', null, 0);

        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/wallet/lookup')
            ->withParsedBody(['telefono' => '3339990000']);
        $response = $controller->lookup($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(5.0, $body['wallet_balance']);
        $this->assertCount(1, $body['transactions']);
    }

    public function testLookupByUnknownPhoneReturns404(): void
    {
        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/wallet/lookup')
            ->withParsedBody(['telefono' => '0000000000']);
        $response = $controller->lookup($request, new Response());

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowReturnsMaxRedeemable(): void
    {
        $user = User::create([
            'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990001',
            'email' => 'anna@example.com', 'data_nascita' => '1995-05-20', 'wallet_balance' => 0,
        ]);
        (new WalletService())->createExpense($user, 1000, '2026-08-16', null, 0);
        // saldo dopo: 50

        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/users/{$user->id}/wallet");
        $response = $controller->show($request, new Response(), ['id' => $user->id]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(50.0, $body['wallet_balance']);
        $this->assertEquals(50.0, $body['max_redeemable']);
    }
}
