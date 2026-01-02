<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.enrollments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'course_id',
        'user_id',
        'did_id',
        'status',
        'enrolled_at',
        'completed_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
