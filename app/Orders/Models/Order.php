<?php

namespace App\Orders\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FULFILLED = 'fulfilled';

    public const INVENTORY_PENDING = 'pending';
    public const INVENTORY_RESERVED = 'reserved';
    public const INVENTORY_FAILED = 'failed';

    protected $connection = 'orders';

    protected $fillable = [
        'id',
        'user_id',
        'status',
        'inventory_status',
        'total_amount',
        'currency',
        'meta',
        'trace_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->id)) {
                $order->id = (string) Str::uuid();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

