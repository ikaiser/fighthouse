<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\Coach;
use App\Models\CoachWalletTransaction;
use App\Models\Expense;
use App\Models\WalletTransaction;
use Illuminate\Database\Capsule\Manager as DB;

class WalletService
{
    public const CASHBACK_RATE = 0.05;
    public const REDEMPTION_STEP = 25.0;

    public function maxRedeemable(Athlete $athlete): float
    {
        return floor($athlete->wallet_balance / self::REDEMPTION_STEP) * self::REDEMPTION_STEP;
    }

    public function creditCoachManually(Coach $coach, float $importoBase): CoachWalletTransaction
    {
        if ($importoBase <= 0) {
            throw new \InvalidArgumentException('importo deve essere maggiore di zero');
        }

        return DB::connection()->transaction(function () use ($coach, $importoBase) {
            $accredito = round($importoBase * self::CASHBACK_RATE, 2);
            return $this->recordCoachTransaction($coach, null, 'accredito', $accredito);
        });
    }

    public function createExpense(Athlete $athlete, float $importo, string $dataSpesa, ?string $note, float $walletUsato): Expense
    {
        if ($importo <= 0) {
            throw new \InvalidArgumentException('importo deve essere maggiore di zero');
        }

        $maxRedeemable = $this->maxRedeemable($athlete);
        if ($walletUsato > 0 && abs($walletUsato - $maxRedeemable) > 0.001) {
            throw new \InvalidArgumentException('wallet_usato deve corrispondere alla soglia massima raggiunta');
        }

        return DB::connection()->transaction(function () use ($athlete, $importo, $dataSpesa, $note, $walletUsato) {
            $expense = Expense::create([
                'user_id' => $athlete->id,
                'importo' => $importo,
                'wallet_usato' => $walletUsato,
                'data_spesa' => $dataSpesa,
                'note' => $note,
            ]);

            $accredito = round($importo * self::CASHBACK_RATE, 2);
            $this->recordAthleteTransaction($athlete, $expense->id, 'accredito', $accredito);
            $this->recordCoachTransaction($athlete->coach, $expense->id, 'accredito', $accredito);

            if ($walletUsato > 0) {
                $this->recordAthleteTransaction($athlete, $expense->id, 'scarico', -$walletUsato);
            }

            return $expense->fresh();
        });
    }

    public function updateExpense(Expense $expense, float $importo, string $dataSpesa, ?string $note, float $walletUsato): Expense
    {
        if ($importo <= 0) {
            throw new \InvalidArgumentException('importo deve essere maggiore di zero');
        }

        $athlete = $expense->athlete;

        return DB::connection()->transaction(function () use ($expense, $athlete, $importo, $dataSpesa, $note, $walletUsato) {
            $oldAccredito = round($expense->importo * self::CASHBACK_RATE, 2);
            $newAccredito = round($importo * self::CASHBACK_RATE, 2);
            $deltaAccredito = round($newAccredito - $oldAccredito, 2);

            $oldNet = $oldAccredito - $expense->wallet_usato;
            $newNet = $newAccredito - $walletUsato;
            $delta = round($newNet - $oldNet, 2);

            if ($athlete->wallet_balance + $delta < 0) {
                throw new \RuntimeException('operazione non consentita: saldo wallet risulterebbe negativo');
            }

            $expense->update([
                'importo' => $importo,
                'wallet_usato' => $walletUsato,
                'data_spesa' => $dataSpesa,
                'note' => $note,
            ]);

            if (abs($delta) > 0.001) {
                $this->recordAthleteTransaction($athlete, $expense->id, 'rettifica', $delta);
            }

            // Il coach non riceve mai lo scarico: la sua rettifica riflette solo
            // la variazione dell'accredito, non l'intero delta netto dell'atleta.
            if (abs($deltaAccredito) > 0.001) {
                $this->recordCoachTransaction($athlete->coach, $expense->id, 'rettifica', $deltaAccredito);
            }

            return $expense->fresh();
        });
    }

    public function deleteExpense(Expense $expense): void
    {
        $athlete = $expense->athlete;

        DB::connection()->transaction(function () use ($expense, $athlete) {
            $accredito = round($expense->importo * self::CASHBACK_RATE, 2);
            $scarico = $expense->wallet_usato;
            $storno = round(-($accredito - $scarico), 2);

            if (abs($storno) > 0.001) {
                $this->recordAthleteTransaction($athlete, $expense->id, 'rettifica', $storno);
            }
            if (abs($accredito) > 0.001) {
                $this->recordCoachTransaction($athlete->coach, $expense->id, 'rettifica', -$accredito);
            }

            $expense->delete();
        });
    }

    private function recordAthleteTransaction(Athlete $athlete, ?int $expenseId, string $tipo, float $importo): WalletTransaction
    {
        $newBalance = round($athlete->wallet_balance + $importo, 2);

        $tx = WalletTransaction::create([
            'user_id' => $athlete->id,
            'expense_id' => $expenseId,
            'tipo' => $tipo,
            'importo' => $importo,
            'saldo_dopo' => $newBalance,
        ]);

        $athlete->wallet_balance = $newBalance;
        $athlete->save();

        return $tx;
    }

    private function recordCoachTransaction(Coach $coach, ?int $expenseId, string $tipo, float $importo): CoachWalletTransaction
    {
        $newBalance = round($coach->wallet_balance + $importo, 2);

        $tx = CoachWalletTransaction::create([
            'coach_id' => $coach->id,
            'expense_id' => $expenseId,
            'tipo' => $tipo,
            'importo' => $importo,
            'saldo_dopo' => $newBalance,
        ]);

        $coach->wallet_balance = $newBalance;
        $coach->save();

        return $tx;
    }
}
