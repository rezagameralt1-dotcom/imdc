<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LinkingEvent extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.linking_events';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'event_type',
        'payload',
        'actor_user_id',
        'trace_id',
        'created_at',
    ];

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
