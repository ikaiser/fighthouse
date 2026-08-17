<?php

namespace Tests\Services;

use App\Models\Athlete;
use App\Models\Coach;
use App\Models\Expense;
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

    private function makeCoach(float $balance = 0): Coach
    {
        return Coach::create([
            'nome' => 'Carlo', 'cognome' => 'Bianchi', 'telefono' => uniqid('coach'),
            'email' => 'coach@example.com', 'wallet_balance' => $balance,
        ]);
    }

    private function makeAthlete(int $coachId, float $balance = 0): Athlete
    {
        return Athlete::create([
            'nome' => 'Mario', 'cognome' => 'Rossi', 'telefono' => uniqid('3'),
            'email' => 'mario@example.com', 'data_nascita' => '1990-01-01',
            'coach_id' => $coachId, 'wallet_balance' => $balance,
        ]);
    }

    public function testMaxRedeemableFloorsToNearest25(): void
    {
        $athlete = $this->makeAthlete($this->makeCoach()->id, 83);
        $this->assertEquals(75.0, $this->service->maxRedeemable($athlete));
    }

    public function testMaxRedeemableIsZeroBelow25(): void
    {
        $athlete = $this->makeAthlete($this->makeCoach()->id, 24.99);
        $this->assertEquals(0.0, $this->service->maxRedeemable($athlete));
    }

    public function testCreateExpenseCreditsFivePercentOnGrossAmount(): void
    {
        $coach = $this->makeCoach();
        $athlete = $this->makeAthlete($coach->id);
        $expense = $this->service->createExpense($athlete, 100.0, '2026-08-16', null, 0);

        $this->assertEquals(5.0, $expense->athlete->wallet_balance);
        $this->assertEquals(100.0, $expense->importo);
    }

    public function testCreateExpenseCreditsCoachSameFivePercentAsAthlete(): void
    {
        $coach = $this->makeCoach();
        $athlete = $this->makeAthlete($coach->id);
        $this->service->createExpense($athlete, 100.0, '2026-08-16', null, 0);

        $this->assertEquals(5.0, $coach->fresh()->wallet_balance);
    }

    public function testCreateExpenseWithWalletRedemptionDeductsFromAthleteButNotCoach(): void
    {
        $coach = $this->makeCoach();
        $athlete = $this->makeAthlete($coach->id, 50);
        $expense = $this->service->createExpense($athlete, 100.0, '2026-08-16', null, 50.0);

        // atleta: 50 (saldo iniziale) - 50 (scarico) + 5 (5% di 100) = 5
        $this->assertEquals(5.0, $expense->fresh()->athlete->wallet_balance);
        $this->assertEquals(50.0, $expense->wallet_usato);

        // il coach non riceve mai lo scarico: solo il 5% di accredito
        $this->assertEquals(5.0, $coach->fresh()->wallet_balance);
    }

    public function testCreateExpenseRejectsWalletUsatoNotMatchingThreshold(): void
    {
        $athlete = $this->makeAthlete($this->makeCoach()->id, 50);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createExpense($athlete, 100.0, '2026-08-16', null, 30.0);
    }

    public function testCreateExpenseRejectsZeroOrNegativeImporto(): void
    {
        $athlete = $this->makeAthlete($this->makeCoach()->id);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createExpense($athlete, 0.0, '2026-08-16', null, 0);
    }

    public function testUpdateExpenseRecalculatesBalanceWithRettificaForAthleteAndCoach(): void
    {
        $coach = $this->makeCoach();
        $athlete = $this->makeAthlete($coach->id);
        $expense = $this->service->createExpense($athlete, 100.0, '2026-08-16', null, 0);
        // saldo dopo creazione: atleta 5, coach 5

        $updated = $this->service->updateExpense($expense, 200.0, '2026-08-16', null, 0);
        // nuovo accredito: 10, vecchio: 5, delta: +5 -> saldo 10 per entrambi

        $this->assertEquals(10.0, $updated->fresh()->athlete->wallet_balance);
        $this->assertEquals(10.0, $coach->fresh()->wallet_balance);
    }

    public function testUpdateExpenseRettificaOnCoachIgnoresWalletUsatoChange(): void
    {
        $coach = $this->makeCoach();
        $athlete = $this->makeAthlete($coach->id, 50);
        $expense = $this->service->createExpense($athlete, 100.0, '2026-08-16', null, 50.0);
        // saldo dopo creazione: atleta 5, coach 5

        // stesso importo, ma ora l'atleta smette di usare il wallet: cambia solo lo scarico, non l'accredito
        $updated = $this->service->updateExpense($expense, 100.0, '2026-08-16', null, 0);

        // atleta: 5 + 50 (lo scarico non è più applicato) = 55
        $this->assertEquals(55.0, $updated->fresh()->athlete->wallet_balance);
        // coach: l'accredito non cambia, quindi il saldo resta invariato
        $this->assertEquals(5.0, $coach->fresh()->wallet_balance);
    }

    public function testUpdateExpenseRejectsIfBalanceWouldGoNegative(): void
    {
        $coach = $this->makeCoach();
        $athlete = $this->makeAthlete($coach->id);
        $expenseA = $this->service->createExpense($athlete, 1000.0, '2026-08-16', null, 0);
        // saldo dopo A: 50
        $this->service->createExpense($athlete->fresh(), 100.0, '2026-08-16', null, 50.0);
        // saldo dopo B: 50 - 50 (scarico) + 5 (5% di 100) = 5

        // Riducendo drasticamente l'importo di A, il suo contributo storico (+50) sparirebbe:
        // saldo 5 - 50 = -45, non consentito.
        $this->expectException(\RuntimeException::class);
        $this->service->updateExpense($expenseA, 0.01, '2026-08-16', null, 0);
    }

    public function testDeleteExpenseReversesAccreditoAndScaricoForAthleteAndCoach(): void
    {
        $coach = $this->makeCoach();
        $athlete = $this->makeAthlete($coach->id, 50);
        $expense = $this->service->createExpense($athlete, 100.0, '2026-08-16', null, 50.0);
        // saldo dopo creazione: atleta 5, coach 5

        $this->service->deleteExpense($expense);

        $this->assertEquals(50.0, $athlete->fresh()->wallet_balance);
        $this->assertEquals(0.0, $coach->fresh()->wallet_balance);
        $this->assertNull(Expense::find($expense->id));
    }
}
