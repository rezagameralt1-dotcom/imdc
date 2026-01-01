<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DidNftLink extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.did_nft_links';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'did_id',
        'nft_id',
        'nft_db_connection',
        'role',
        'created_by',
    ];

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
