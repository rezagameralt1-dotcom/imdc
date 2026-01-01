<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderNftLink extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.order_nft_links';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_id',
        'nft_id',
        'orders_db_connection',
        'nfts_db_connection',
        'purpose',
        'created_by',
    ];

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
