<?php

namespace App\Products\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = "string";
    use HasFactory;

    protected $connection = 'products';

    protected $fillable = [
        'id',
        'sku',
        'name',
        'description',
        'price',
        'currency',
        'status',
        'metadata',
        'seller_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->id)) {
                $product->id = (string) Str::uuid();
            }
        });
    }
}



