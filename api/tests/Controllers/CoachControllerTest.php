<?php

namespace Tests\Controllers;

use App\Controllers\CoachController;
use App\Models\Athlete;
use App\Models\Coach;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\TestDatabase;

class CoachControllerTest extends TestCase
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

    public function testCreateCoachPersistsFields(): void
    {
        $controller = new CoachController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/coaches')
            ->withParsedBody(['nome' => 'Carlo', 'cognome' => 'Bianchi', 'telefono' => '3335551111', 'email' => 'c@example.com']);
        $response = $controller->create($request, new Response());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(1, Coach::count());
        $this->assertNull(Coach::first()->data_nascita);
    }

    public function testCreateCoachPersistsOptionalDataNascita(): void
    {
        $controller = new CoachController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/coaches')
            ->withParsedBody([
                'nome' => 'Carlo', 'cognome' => 'Bianchi', 'telefono' => '3335551111',
                'email' => 'c@example.com', 'data_nascita' => '1988-11-14',
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('1988-11-14', Coach::first()->data_nascita->format('Y-m-d'));
    }

    public function testCreateCoachRejectsInvalidDataNascita(): void
    {
        $controller = new CoachController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/coaches')
            ->withParsedBody([
                'nome' => 'Carlo', 'cognome' => 'Bianchi', 'telefono' => '3335551111',
                'email' => 'c@example.com', 'data_nascita' => 'not-a-date',
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(0, Coach::count());
    }

    public function testCreateCoachRejectsDuplicatePhone(): void
    {
        Coach::create(['nome' => 'A', 'cognome' => 'B', 'telefono' => '3335551111', 'email' => 'a@example.com', 'wallet_balance' => 0]);

        $controller = new CoachController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/coaches')
            ->withParsedBody(['nome' => 'C', 'cognome' => 'D', 'telefono' => '3335551111', 'email' => 'c@example.com']);
        $response = $controller->create($request, new Response());

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testDeleteCoachRejectsIfAthletesAssigned(): void
    {
        $coach = Coach::create(['nome' => 'A', 'cognome' => 'B', 'telefono' => '3335551111', 'email' => 'a@example.com', 'wallet_balance' => 0]);
        Athlete::create([
            'nome' => 'X', 'cognome' => 'Y', 'telefono' => '3339990000', 'email' => 'x@example.com',
            'data_nascita' => '1990-01-01', 'coach_id' => $coach->id, 'wallet_balance' => 0,
        ]);

        $controller = new CoachController();
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/coaches/{$coach->id}");
        $response = $controller->delete($request, new Response(), ['id' => $coach->id]);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertNotNull(Coach::find($coach->id));
    }

    public function testIndexReturnsPaginatedEnvelopeWhenPageRequested(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Coach::create(['nome' => 'A', 'cognome' => "B{$i}", 'telefono' => "333555000{$i}", 'email' => 'a@example.com', 'wallet_balance' => 0]);
        }

        $controller = new CoachController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/coaches')
            ->withQueryParams(['page' => '2', 'per_page' => '2']);
        $response = $controller->index($request, new Response());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertCount(1, $body['data']);
        $this->assertEquals(3, $body['meta']['total']);
        $this->assertEquals(2, $body['meta']['current_page']);
        $this->assertEquals(2, $body['meta']['last_page']);
    }

    public function testDeleteCoachSucceedsWithoutAthletes(): void
    {
        $coach = Coach::create(['nome' => 'A', 'cognome' => 'B', 'telefono' => '3335551111', 'email' => 'a@example.com', 'wallet_balance' => 0]);

        $controller = new CoachController();
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/coaches/{$coach->id}");
        $response = $controller->delete($request, new Response(), ['id' => $coach->id]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull(Coach::find($coach->id));
    }
}
