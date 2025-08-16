<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['shop_id', 'name', 'sku', 'price', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
