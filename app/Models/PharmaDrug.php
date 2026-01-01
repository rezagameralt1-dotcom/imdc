<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PharmaDrug extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.pharma_drugs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'generic_name',
        'description',
        'warnings',
        'contraindications',
        'metadata',
    ];

    protected $casts = [
        'warnings' => 'array',
        'contraindications' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function interactionsAsDrug1()
    {
        return $this->hasMany(PharmaInteraction::class, 'drug1_id')->on('core');
    }

    public function interactionsAsDrug2()
    {
        return $this->hasMany(PharmaInteraction::class, 'drug2_id')->on('core');
    }
}
