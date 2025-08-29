<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['sku','title','description','category_id','price','currency','is_active','meta'];
    protected $casts = ['meta' => 'array', 'is_active' => 'boolean'];

    public function category() { return $this->belongsTo(Category::class); }
    public function inventory() { return $this->hasOne(Inventory::class); }
    public function orderItems() { return $this->hasMany(OrderItem::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
}
