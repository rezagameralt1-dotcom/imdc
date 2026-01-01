<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DaoProposal extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.dao_proposals';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'description',
        'status',
        'created_by_did',
        'voting_starts_at',
        'voting_ends_at',
        'quorum',
        'metadata',
    ];

    protected $casts = [
        'voting_starts_at' => 'datetime',
        'voting_ends_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function votes()
    {
        return $this->hasMany(DaoVote::class, 'proposal_id')->on('core');
    }
}
