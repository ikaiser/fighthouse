<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    public $timestamps = false;
    protected $fillable = ['username', 'password_hash', 'failed_attempts', 'locked_until'];
}
