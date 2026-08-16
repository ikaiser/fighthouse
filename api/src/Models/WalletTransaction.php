<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'expense_id', 'tipo', 'importo', 'saldo_dopo'];
    protected $casts = [
        'importo' => 'float',
        'saldo_dopo' => 'float',
        'created_at' => 'datetime',
    ];
}
