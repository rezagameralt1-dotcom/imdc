<?php

namespace App\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InventoryReservation extends Model
{
    use HasFactory;

    protected $connection = 'inventory';

    protected $fillable = [
        'id',
        'product_id',
        'order_id',
        'quantity',
        'status',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryReservation $reservation) {
            if (empty($reservation->id)) {
                $reservation->id = (string) Str::uuid();
            }
        });
    }
}

