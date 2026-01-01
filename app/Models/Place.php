<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.places';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'description',
        'type',
        'latitude',
        'longitude',
        'altitude',
        'owner_did',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'altitude' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function links()
    {
        return $this->hasMany(PlaceLink::class, 'place_id')->on('core');
    }
}
