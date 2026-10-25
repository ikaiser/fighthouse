<?php

namespace Tests\Controllers;

use App\Controllers\WalletController;
use App\Models\Athlete;
use App\Models\Coach;
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

    private function makeCoach(?string $telefono = null): Coach
    {
        return Coach::create([
            'nome' => 'Carlo', 'cognome' => 'Bianchi', 'telefono' => $telefono ?? uniqid('coach'),
            'email' => 'coach@example.com', 'wallet_balance' => 0,
        ]);
    }

    public function testLookupByPhoneReturnsAthleteBalanceAndHistory(): void
    {
        $athlete = Athlete::create([
            'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990000',
            'email' => 'anna@example.com', 'data_nascita' => '1995-05-20',
            'coach_id' => $this->makeCoach()->id, 'wallet_balance' => 0,
        ]);
        (new WalletService())->createExpense($athlete, 100, '2026-08-16', null, 0);

        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/wallet/lookup')
            ->withParsedBody(['telefono' => '3339990000']);
        $response = $controller->lookup($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('athlete', $body['type']);
        $this->assertEquals(5.0, $body['wallet_balance']);
        $this->assertCount(1, $body['transactions']);
    }

    public function testLookupByPhoneReturnsCoachBalanceAndHistory(): void
    {
        $coach = $this->makeCoach('3221110000');
        $athlete = Athlete::create([
            'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990002',
            'email' => 'anna@example.com', 'data_nascita' => '1995-05-20',
            'coach_id' => $coach->id, 'wallet_balance' => 0,
        ]);
        (new WalletService())->createExpense($athlete, 100, '2026-08-16', null, 0);

        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/wallet/lookup')
            ->withParsedBody(['telefono' => '3221110000']);
        $response = $controller->lookup($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('coach', $body['type']);
        $this->assertEquals(5.0, $body['wallet_balance']);
        $this->assertArrayNotHasKey('max_redeemable', $body);
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
        $athlete = Athlete::create([
            'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990001',
            'email' => 'anna@example.com', 'data_nascita' => '1995-05-20',
            'coach_id' => $this->makeCoach()->id, 'wallet_balance' => 0,
        ]);
        (new WalletService())->createExpense($athlete, 1000, '2026-08-16', null, 0);
        // saldo dopo: 50

        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/athletes/{$athlete->id}/wallet");
        $response = $controller->show($request, new Response(), ['id' => $athlete->id]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(50.0, $body['wallet_balance']);
        $this->assertEquals(50.0, $body['max_redeemable']);
    }

    public function testShowCoachReturnsBalanceWithoutMaxRedeemable(): void
    {
        $coach = $this->makeCoach();
        $athlete = Athlete::create([
            'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990003',
            'email' => 'anna@example.com', 'data_nascita' => '1995-05-20',
            'coach_id' => $coach->id, 'wallet_balance' => 0,
        ]);
        (new WalletService())->createExpense($athlete, 1000, '2026-08-16', null, 0);

        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/coaches/{$coach->id}/wallet");
        $response = $controller->showCoach($request, new Response(), ['id' => $coach->id]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(50.0, $body['wallet_balance']);
        $this->assertArrayNotHasKey('max_redeemable', $body);
    }

    public function testCreditCoachAddsFivePercentOfGivenAmount(): void
    {
        $coach = $this->makeCoach();

        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/coaches/{$coach->id}/wallet/credit")
            ->withParsedBody(['importo' => 70]);
        $response = $controller->creditCoach($request, new Response(), ['id' => $coach->id]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(3.5, $body['wallet_balance']);
        $this->assertCount(1, $body['transactions']);
    }

    public function testCreditCoachRejectsZeroOrNegativeImporto(): void
    {
        $coach = $this->makeCoach();

        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/coaches/{$coach->id}/wallet/credit")
            ->withParsedBody(['importo' => 0]);
        $response = $controller->creditCoach($request, new Response(), ['id' => $coach->id]);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(0.0, $coach->fresh()->wallet_balance);
    }

    public function testCreditCoachReturns404ForUnknownCoach(): void
    {
        $controller = new WalletController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/coaches/999/wallet/credit')
            ->withParsedBody(['importo' => 70]);
        $response = $controller->creditCoach($request, new Response(), ['id' => 999]);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
