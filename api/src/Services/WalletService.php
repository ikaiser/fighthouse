<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Capsule\Manager as DB;

class WalletService
{
    public const CASHBACK_RATE = 0.05;
    public const REDEMPTION_STEP = 25.0;

    public function maxRedeemable(User $user): float
    {
        return floor($user->wallet_balance / self::REDEMPTION_STEP) * self::REDEMPTION_STEP;
    }

    public function createExpense(User $user, float $importo, string $dataSpesa, ?string $note, float $walletUsato): Expense
    {
        if ($importo <= 0) {
            throw new \InvalidArgumentException('importo deve essere maggiore di zero');
        }

        $maxRedeemable = $this->maxRedeemable($user);
        if ($walletUsato > 0 && abs($walletUsato - $maxRedeemable) > 0.001) {
            throw new \InvalidArgumentException('wallet_usato deve corrispondere alla soglia massima raggiunta');
        }

        return DB::connection()->transaction(function () use ($user, $importo, $dataSpesa, $note, $walletUsato) {
            $expense = Expense::create([
                'user_id' => $user->id,
                'importo' => $importo,
                'wallet_usato' => $walletUsato,
                'data_spesa' => $dataSpesa,
                'note' => $note,
            ]);

            $accredito = round($importo * self::CASHBACK_RATE, 2);
            $this->recordTransaction($user, $expense->id, 'accredito', $accredito);

            if ($walletUsato > 0) {
                $this->recordTransaction($user, $expense->id, 'scarico', -$walletUsato);
            }

            return $expense->fresh();
        });
    }

    public function updateExpense(Expense $expense, float $importo, string $dataSpesa, ?string $note, float $walletUsato): Expense
    {
        if ($importo <= 0) {
            throw new \InvalidArgumentException('importo deve essere maggiore di zero');
        }

        $user = $expense->user;

        return DB::connection()->transaction(function () use ($expense, $user, $importo, $dataSpesa, $note, $walletUsato) {
            $oldNet = round($expense->importo * self::CASHBACK_RATE, 2) - $expense->wallet_usato;
            $newNet = round($importo * self::CASHBACK_RATE, 2) - $walletUsato;
            $delta = round($newNet - $oldNet, 2);

            if ($user->wallet_balance + $delta < 0) {
                throw new \RuntimeException('operazione non consentita: saldo wallet risulterebbe negativo');
            }

            $expense->update([
                'importo' => $importo,
                'wallet_usato' => $walletUsato,
                'data_spesa' => $dataSpesa,
                'note' => $note,
            ]);

            if (abs($delta) > 0.001) {
                $this->recordTransaction($user, $expense->id, 'rettifica', $delta);
            }

            return $expense->fresh();
        });
    }

    public function deleteExpense(Expense $expense): void
    {
        $user = $expense->user;

        DB::connection()->transaction(function () use ($expense, $user) {
            $accredito = round($expense->importo * self::CASHBACK_RATE, 2);
            $scarico = $expense->wallet_usato;
            $storno = round(-($accredito - $scarico), 2);

            if (abs($storno) > 0.001) {
                $this->recordTransaction($user, $expense->id, 'rettifica', $storno);
            }

            $expense->delete();
        });
    }

    private function recordTransaction(User $user, ?int $expenseId, string $tipo, float $importo): WalletTransaction
    {
        $newBalance = round($user->wallet_balance + $importo, 2);

        $tx = WalletTransaction::create([
            'user_id' => $user->id,
            'expense_id' => $expenseId,
            'tipo' => $tipo,
            'importo' => $importo,
            'saldo_dopo' => $newBalance,
        ]);

        $user->wallet_balance = $newBalance;
        $user->save();

        return $tx;
    }
}
