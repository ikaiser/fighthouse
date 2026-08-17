<?php

namespace Tests\Models;

use App\Models\Athlete;
use App\Models\Coach;
use App\Models\Expense;
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

    public function testAthleteHasManyExpensesTransactionsAndBelongsToCoach(): void
    {
        $coach = Coach::create([
            'nome' => 'Carlo', 'cognome' => 'Bianchi', 'telefono' => '3330001111',
            'email' => 'carlo@example.com', 'wallet_balance' => 0,
        ]);

        $athlete = Athlete::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'telefono' => '3331112222',
            'email' => 'mario@example.com', 'data_nascita' => '1990-01-01',
            'coach_id' => $coach->id, 'wallet_balance' => 0,
        ]);

        $expense = Expense::create([
            'user_id' => $athlete->id, 'importo' => 100, 'wallet_usato' => 0, 'data_spesa' => '2026-08-16',
        ]);

        WalletTransaction::create([
            'user_id' => $athlete->id, 'expense_id' => $expense->id, 'tipo' => 'accredito',
            'importo' => 5, 'saldo_dopo' => 5,
        ]);

        $this->assertCount(1, $athlete->expenses);
        $this->assertCount(1, $athlete->walletTransactions);
        $this->assertEquals('Mario', $expense->athlete->nome);
        $this->assertEquals('Carlo', $athlete->coach->nome);
        $this->assertCount(1, $coach->athletes);
    }
}
