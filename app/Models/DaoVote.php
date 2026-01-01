<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DaoVote extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.dao_votes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'proposal_id',
        'voter_did',
        'vote',
        'weight',
        'created_by',
    ];

    protected $casts = [
        'weight' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function proposal()
    {
        return $this->belongsTo(DaoProposal::class, 'proposal_id');
    }
}
