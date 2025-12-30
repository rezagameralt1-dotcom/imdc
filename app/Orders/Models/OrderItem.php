<?php

namespace App\Orders\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrderItem extends Model
{
    use HasFactory;

    protected $connection = 'orders';

    protected $fillable = [
        'id',
        'order_id',
        'product_id',
        'product_name',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderItem $item) {
            if (empty($item->id)) {
                $item->id = (string) Str::uuid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

