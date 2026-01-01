<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PharmaEvent extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.pharma_events';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'event_type',
        'payload',
        'actor_user_id',
        'trace_id',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false; // Managed manually for created_at
}
