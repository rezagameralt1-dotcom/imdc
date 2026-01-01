<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $connection = 'core';

    protected $fillable = [
        'user_id',
        'scope',
        'key',
        'request_hash',
        'response_code',
        'response_body',
        'resource_id',
    ];

    protected $casts = [
        'response_code' => 'integer',
        'response_body' => 'string',
    ];
}
