<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['user_id', 'importo', 'wallet_usato', 'data_spesa', 'note'];
    protected $casts = [
        'importo' => 'float',
        'wallet_usato' => 'float',
        'data_spesa' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
