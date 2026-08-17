<?php

namespace Tests\Controllers;

use App\Controllers\AthleteController;
use App\Models\Athlete;
use App\Models\Coach;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\TestDatabase;

class AthleteControllerTest extends TestCase
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

    private function makeCoach(): Coach
    {
        return Coach::create([
            'nome' => 'Carlo', 'cognome' => 'Bianchi', 'telefono' => uniqid('coach'),
            'email' => 'coach@example.com', 'wallet_balance' => 0,
        ]);
    }

    public function testCreateAthletePersistsAllFieldsIncludingCoach(): void
    {
        $coach = $this->makeCoach();

        $controller = new AthleteController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/athletes')
            ->withParsedBody([
                'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990000',
                'email' => 'anna@example.com', 'data_nascita' => '1995-05-20', 'coach_id' => $coach->id,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(1, Athlete::count());
        $this->assertEquals($coach->id, Athlete::first()->coach_id);
    }

    public function testCreateAthleteRejectsMissingCoach(): void
    {
        $controller = new AthleteController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/athletes')
            ->withParsedBody([
                'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990000',
                'email' => 'anna@example.com', 'data_nascita' => '1995-05-20',
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateAthleteRejectsUnknownCoach(): void
    {
        $controller = new AthleteController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/athletes')
            ->withParsedBody([
                'nome' => 'Anna', 'cognome' => 'Bianchi', 'telefono' => '3339990000',
                'email' => 'anna@example.com', 'data_nascita' => '1995-05-20', 'coach_id' => 999,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateAthleteRejectsDuplicatePhone(): void
    {
        $coach = $this->makeCoach();
        Athlete::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '3339990000',
            'email' => 'a@example.com', 'data_nascita' => '1990-01-01', 'coach_id' => $coach->id, 'wallet_balance' => 0,
        ]);

        $controller = new AthleteController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/athletes')
            ->withParsedBody([
                'nome' => 'C', 'cognome' => 'D', 'telefono' => '3339990000',
                'email' => 'c@example.com', 'data_nascita' => '1990-01-01', 'coach_id' => $coach->id,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testCreateAthleteRejectsPhoneAlreadyUsedByCoach(): void
    {
        $coach = $this->makeCoach();

        $controller = new AthleteController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/athletes')
            ->withParsedBody([
                'nome' => 'C', 'cognome' => 'D', 'telefono' => $coach->telefono,
                'email' => 'c@example.com', 'data_nascita' => '1990-01-01', 'coach_id' => $coach->id,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testUpdateAthleteChangesFields(): void
    {
        $coach = $this->makeCoach();
        $athlete = Athlete::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '3330000001',
            'email' => 'a@example.com', 'data_nascita' => '1990-01-01', 'coach_id' => $coach->id, 'wallet_balance' => 0,
        ]);

        $controller = new AthleteController();
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/api/athletes/{$athlete->id}")
            ->withParsedBody([
                'nome' => 'Aggiornato', 'cognome' => 'B', 'telefono' => '3330000001',
                'email' => 'a@example.com', 'data_nascita' => '1990-01-01', 'coach_id' => $coach->id,
            ]);
        $response = $controller->update($request, new Response(), ['id' => $athlete->id]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Aggiornato', $athlete->fresh()->nome);
    }

    public function testDeleteAthleteRemovesRow(): void
    {
        $coach = $this->makeCoach();
        $athlete = Athlete::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '3330000002',
            'email' => 'a@example.com', 'data_nascita' => '1990-01-01', 'coach_id' => $coach->id, 'wallet_balance' => 0,
        ]);

        $controller = new AthleteController();
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/athletes/{$athlete->id}");
        $response = $controller->delete($request, new Response(), ['id' => $athlete->id]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull(Athlete::find($athlete->id));
    }

    public function testShowReturns404ForUnknownAthlete(): void
    {
        $controller = new AthleteController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/athletes/999');
        $response = $controller->show($request, new Response(), ['id' => 999]);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
