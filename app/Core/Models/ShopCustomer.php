<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopCustomer extends Model
{
    use HasFactory;

    protected $connection = 'core';

    protected $fillable = [
        'user_id',
        'shop_customer_id',
    ];
}

