<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasUuids;

    protected $connection = 'core';
    protected $table = 'pub.courses';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'description',
        'level',
        'language',
        'teacher_user_id',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'course_id');
    }
}
