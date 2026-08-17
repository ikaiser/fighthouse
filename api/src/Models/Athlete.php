<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Athlete extends Model
{
    protected $fillable = ['nome', 'cognome', 'telefono', 'email', 'data_nascita', 'wallet_balance', 'coach_id'];
    protected $casts = [
        'wallet_balance' => 'float',
        'data_nascita' => 'date:Y-m-d',
    ];

    public function coach()
    {
        return $this->belongsTo(Coach::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'user_id');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'user_id')->orderByDesc('created_at');
    }
}
