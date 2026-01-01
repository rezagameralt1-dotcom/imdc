<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PharmaInteraction extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.pharma_interactions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'drug1_id',
        'drug2_id',
        'severity',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function drug1()
    {
        return $this->belongsTo(PharmaDrug::class, 'drug1_id')->on('core');
    }

    public function drug2()
    {
        return $this->belongsTo(PharmaDrug::class, 'drug2_id')->on('core');
    }
}
