<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Order extends Model
{
    protected $fillable = [
        'user_id','status','currency','subtotal','discount_total',
        'tax_total','shipping_total','total_amount','meta'
    ];
    protected $casts = ['meta' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function vouchers() { return $this->hasMany(AccountingVoucher::class); }
}
