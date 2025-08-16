<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = ['no', 'issued_at'];

    protected $casts = ['issued_at' => 'datetime'];
}
