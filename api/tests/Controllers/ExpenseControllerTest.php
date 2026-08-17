<?php

namespace Tests\Controllers;

use App\Controllers\ExpenseController;
use App\Models\Athlete;
use App\Models\Coach;
use App\Services\WalletService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\TestDatabase;

class ExpenseControllerTest extends TestCase
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

    private function makeAthlete(): Athlete
    {
        $coach = Coach::create(['nome' => 'C', 'cognome' => 'B', 'telefono' => uniqid('coach'), 'email' => 'c@example.com', 'wallet_balance' => 0]);

        return Athlete::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => uniqid('3'), 'email' => 'a@example.com',
            'data_nascita' => '1990-01-01', 'coach_id' => $coach->id, 'wallet_balance' => 0,
        ]);
    }

    public function testCreateExpenseAppliesCashback(): void
    {
        $athlete = $this->makeAthlete();

        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/expenses')
            ->withParsedBody([
                'user_id' => $athlete->id, 'importo' => 100, 'data_spesa' => '2026-08-16', 'wallet_usato' => 0,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(5.0, $athlete->fresh()->wallet_balance);
    }

    public function testCreateExpenseReturns400OnInvalidWalletUsato(): void
    {
        $athlete = $this->makeAthlete();
        $athlete->update(['wallet_balance' => 10]);

        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/expenses')
            ->withParsedBody([
                'user_id' => $athlete->id, 'importo' => 100, 'data_spesa' => '2026-08-16', 'wallet_usato' => 10,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateExpenseReturns404ForUnknownAthlete(): void
    {
        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/expenses')
            ->withParsedBody([
                'user_id' => 999, 'importo' => 100, 'data_spesa' => '2026-08-16', 'wallet_usato' => 0,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteExpenseReversesWallet(): void
    {
        $athlete = $this->makeAthlete();
        $expense = (new WalletService())->createExpense($athlete, 100, '2026-08-16', null, 0);

        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/expenses/{$expense->id}");
        $response = $controller->delete($request, new Response(), ['id' => $expense->id]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals(0.0, $athlete->fresh()->wallet_balance);
    }

    public function testUpdateExpenseReturns409WhenBalanceWouldGoNegative(): void
    {
        $athlete = $this->makeAthlete();
        $service = new WalletService();
        $expenseA = $service->createExpense($athlete, 1000, '2026-08-16', null, 0);
        $service->createExpense($athlete->fresh(), 100, '2026-08-16', null, 50.0);

        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/api/expenses/{$expenseA->id}")
            ->withParsedBody(['importo' => 0.01, 'data_spesa' => '2026-08-16', 'wallet_usato' => 0]);
        $response = $controller->update($request, new Response(), ['id' => $expenseA->id]);

        $this->assertEquals(409, $response->getStatusCode());
    }
}
