<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class StockMovement extends Model
{
    protected $fillable = ['product_id','type','quantity','reason','ref_type','ref_id','performed_by'];

    public function product() { return $this->belongsTo(Product::class); }
    public function actor() { return $this->belongsTo(User::class, 'performed_by'); }
}
