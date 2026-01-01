<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DidOrderLink extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.did_order_links';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'did_id',
        'order_id',
        'order_db_connection',
        'scope',
        'created_by',
    ];

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
