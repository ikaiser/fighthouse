<?php

namespace Tests\Controllers;

use App\Controllers\ExpenseController;
use App\Models\User;
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

    public function testCreateExpenseAppliesCashback(): void
    {
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '333', 'email' => 'a@example.com',
            'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);

        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/expenses')
            ->withParsedBody([
                'user_id' => $user->id, 'importo' => 100, 'data_spesa' => '2026-08-16', 'wallet_usato' => 0,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(5.0, $user->fresh()->wallet_balance);
    }

    public function testCreateExpenseReturns400OnInvalidWalletUsato(): void
    {
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '333', 'email' => 'a@example.com',
            'data_nascita' => '1990-01-01', 'wallet_balance' => 10,
        ]);

        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/expenses')
            ->withParsedBody([
                'user_id' => $user->id, 'importo' => 100, 'data_spesa' => '2026-08-16', 'wallet_usato' => 10,
            ]);
        $response = $controller->create($request, new Response());

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateExpenseReturns404ForUnknownUser(): void
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
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '333', 'email' => 'a@example.com',
            'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);
        $expense = (new WalletService())->createExpense($user, 100, '2026-08-16', null, 0);

        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', "/api/expenses/{$expense->id}");
        $response = $controller->delete($request, new Response(), ['id' => $expense->id]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals(0.0, $user->fresh()->wallet_balance);
    }

    public function testUpdateExpenseReturns409WhenBalanceWouldGoNegative(): void
    {
        $user = User::create([
            'nome' => 'A', 'cognome' => 'B', 'telefono' => '333', 'email' => 'a@example.com',
            'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);
        $service = new WalletService();
        $expenseA = $service->createExpense($user, 1000, '2026-08-16', null, 0);
        $service->createExpense($user->fresh(), 100, '2026-08-16', null, 50.0);

        $controller = new ExpenseController();
        $request = (new ServerRequestFactory())->createServerRequest('PUT', "/api/expenses/{$expenseA->id}")
            ->withParsedBody(['importo' => 0.01, 'data_spesa' => '2026-08-16', 'wallet_usato' => 0]);
        $response = $controller->update($request, new Response(), ['id' => $expenseA->id]);

        $this->assertEquals(409, $response->getStatusCode());
    }
}
