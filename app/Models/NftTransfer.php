<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NftTransfer extends Model
{
    protected $fillable = ['from_wallet_id','to_wallet_id','token_id','contract','transferred_at'];
    protected $casts = ['transferred_at' => 'datetime'];

    public function from(): BelongsTo { return $this->belongsTo(Wallet::class,'from_wallet_id'); }
    public function to(): BelongsTo { return $this->belongsTo(Wallet::class,'to_wallet_id'); }
}
