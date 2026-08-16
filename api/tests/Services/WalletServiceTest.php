<?php

namespace Tests\Services;

use App\Models\Expense;
use App\Models\User;
use App\Services\WalletService;
use PHPUnit\Framework\TestCase;
use Tests\TestDatabase;

class WalletServiceTest extends TestCase
{
    private WalletService $service;

    protected function setUp(): void
    {
        TestDatabase::boot();
        TestDatabase::migrate();
        $this->service = new WalletService();
    }

    protected function tearDown(): void
    {
        TestDatabase::reset();
    }

    private function makeUser(float $balance = 0): User
    {
        return User::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'telefono' => uniqid('3'),
            'email' => 'mario@example.com', 'data_nascita' => '1990-01-01', 'wallet_balance' => $balance,
        ]);
    }

    public function testMaxRedeemableFloorsToNearest25(): void
    {
        $user = $this->makeUser(83);
        $this->assertEquals(75.0, $this->service->maxRedeemable($user));
    }

    public function testMaxRedeemableIsZeroBelow25(): void
    {
        $user = $this->makeUser(24.99);
        $this->assertEquals(0.0, $this->service->maxRedeemable($user));
    }

    public function testCreateExpenseCreditsFivePercentOnGrossAmount(): void
    {
        $user = $this->makeUser(0);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 0);

        $this->assertEquals(5.0, $expense->user->wallet_balance);
        $this->assertEquals(100.0, $expense->importo);
    }

    public function testCreateExpenseWithWalletRedemptionDeductsFromBalance(): void
    {
        $user = $this->makeUser(50);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 50.0);

        // 50 (saldo iniziale) - 50 (scarico) + 5 (5% di 100) = 5
        $this->assertEquals(5.0, $expense->fresh()->user->wallet_balance);
        $this->assertEquals(50.0, $expense->wallet_usato);
    }

    public function testCreateExpenseRejectsWalletUsatoNotMatchingThreshold(): void
    {
        $user = $this->makeUser(50);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createExpense($user, 100.0, '2026-08-16', null, 30.0);
    }

    public function testCreateExpenseRejectsZeroOrNegativeImporto(): void
    {
        $user = $this->makeUser(0);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createExpense($user, 0.0, '2026-08-16', null, 0);
    }

    public function testUpdateExpenseRecalculatesBalanceWithRettifica(): void
    {
        $user = $this->makeUser(0);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 0);
        // saldo dopo creazione: 5

        $updated = $this->service->updateExpense($expense, 200.0, '2026-08-16', null, 0);
        // nuovo accredito: 10, vecchio: 5, delta: +5 -> saldo 10

        $this->assertEquals(10.0, $updated->fresh()->user->wallet_balance);
    }

    public function testUpdateExpenseRejectsIfBalanceWouldGoNegative(): void
    {
        $user = $this->makeUser(0);
        $expenseA = $this->service->createExpense($user, 1000.0, '2026-08-16', null, 0);
        // saldo dopo A: 50 (5% di 1000)
        $this->service->createExpense($user->fresh(), 100.0, '2026-08-16', null, 50.0);
        // saldo dopo B: 50 - 50 (scarico) + 5 (5% di 100) = 5

        // Riducendo drasticamente l'importo di A, il suo contributo storico (+50) sparirebbe:
        // saldo 5 - 50 = -45, non consentito.
        $this->expectException(\RuntimeException::class);
        $this->service->updateExpense($expenseA, 0.01, '2026-08-16', null, 0);
    }

    public function testDeleteExpenseReversesAccreditoAndScarico(): void
    {
        $user = $this->makeUser(50);
        $expense = $this->service->createExpense($user, 100.0, '2026-08-16', null, 50.0);
        // saldo dopo creazione: 5

        $this->service->deleteExpense($expense);

        $this->assertEquals(50.0, $user->fresh()->wallet_balance);
        $this->assertNull(Expense::find($expense->id));
    }
}
