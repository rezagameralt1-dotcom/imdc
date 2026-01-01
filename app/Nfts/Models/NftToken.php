<?php

namespace App\Nfts\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NftToken extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_MINTED = 'minted';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_BURNED = 'burned';

    protected $connection = 'nfts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'nfts_tokens';

    protected $fillable = [
        'id',
        'contract',
        'token_id',
        'owner_user_id',
        'metadata_uri',
        'status',
    ];

    public function transfers(): HasMany
    {
        return $this->hasMany(NftTransfer::class, 'token_id');
    }
}
