<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SkillNft extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.skill_nfts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'course_id',
        'user_id',
        'did_id',
        'nft_token_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id')->on('core');
    }
}
