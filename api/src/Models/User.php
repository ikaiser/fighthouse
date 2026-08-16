<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['nome', 'cognome', 'telefono', 'email', 'data_nascita', 'wallet_balance'];
    protected $casts = [
        'wallet_balance' => 'float',
        'data_nascita' => 'date:Y-m-d',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('created_at');
    }
}
