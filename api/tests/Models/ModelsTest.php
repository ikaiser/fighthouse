<?php

namespace Tests\Models;

use App\Models\Expense;
use App\Models\User;
use App\Models\WalletTransaction;
use PHPUnit\Framework\TestCase;
use Tests\TestDatabase;

class ModelsTest extends TestCase
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

    public function testUserHasManyExpensesAndTransactions(): void
    {
        $user = User::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'telefono' => '3331112222',
            'email' => 'mario@example.com', 'data_nascita' => '1990-01-01', 'wallet_balance' => 0,
        ]);

        $expense = Expense::create([
            'user_id' => $user->id, 'importo' => 100, 'wallet_usato' => 0, 'data_spesa' => '2026-08-16',
        ]);

        WalletTransaction::create([
            'user_id' => $user->id, 'expense_id' => $expense->id, 'tipo' => 'accredito',
            'importo' => 5, 'saldo_dopo' => 5,
        ]);

        $this->assertCount(1, $user->expenses);
        $this->assertCount(1, $user->walletTransactions);
        $this->assertEquals('Mario', $expense->user->nome);
    }
}
