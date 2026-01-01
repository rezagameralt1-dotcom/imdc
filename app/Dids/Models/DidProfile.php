<?php

namespace App\Dids\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DidProfile extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'core';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'did_profiles';

    protected $fillable = [
        'id',
        'user_id',
        'did',
        'wallet_address',
        'display_name',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
