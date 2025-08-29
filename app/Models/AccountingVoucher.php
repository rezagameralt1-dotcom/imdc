<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingVoucher extends Model
{
    protected $fillable = ['order_id','type','amount','status','payload','synced_at'];
    protected $casts = ['payload' => 'array', 'synced_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
}
