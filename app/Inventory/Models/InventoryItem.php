<?php

namespace App\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InventoryItem extends Model
{
    use HasFactory;

    protected $connection = 'inventory';

    protected $fillable = [
        'id',
        'product_id',
        'available_quantity',
        'reserved_quantity',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryItem $item) {
            if (empty($item->id)) {
                $item->id = (string) Str::uuid();
            }
        });
    }
}

