<?php

namespace App\Nfts\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WormLog extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'nfts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'worm_logs';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'event_type',
        'entity_type',
        'entity_id',
        'payload_json',
        'prev_hash',
        'hash',
        'created_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];
}
