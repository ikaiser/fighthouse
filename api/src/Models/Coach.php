<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coach extends Model
{
    protected $fillable = ['nome', 'cognome', 'telefono', 'email', 'data_nascita', 'wallet_balance'];
    protected $casts = [
        'wallet_balance' => 'float',
        'data_nascita' => 'date:Y-m-d',
    ];

    public function athletes()
    {
        return $this->hasMany(Athlete::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(CoachWalletTransaction::class)->orderByDesc('created_at');
    }
}
