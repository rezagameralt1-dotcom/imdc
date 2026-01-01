<?php

namespace App\Nfts\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NftTransfer extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_REJECTED = 'rejected';

    protected $connection = 'nfts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'nfts_transfers';

    protected $fillable = [
        'id',
        'token_id',
        'from_user_id',
        'to_user_id',
        'idempotency_key',
        'requested_by_user_id',
        'status',
    ];

    public function token(): BelongsTo
    {
        return $this->belongsTo(NftToken::class, 'token_id');
    }
}
