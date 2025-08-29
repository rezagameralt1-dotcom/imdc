<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['product_id','stock_on_hand','stock_reserved','reorder_level'];
    protected $appends = ['stock_available'];

    public function product() { return $this->belongsTo(Product::class); }

    public function getStockAvailableAttribute(): int {
        return max(($this->stock_on_hand ?? 0) - ($this->stock_reserved ?? 0), 0);
    }
}
