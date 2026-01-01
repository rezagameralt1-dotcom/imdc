<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlaceLink extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.place_links';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'place_id',
        'nft_id',
        'did_id',
        'link_type',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id')->on('core');
    }
}
