<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassModel extends Model
{
    protected $table = 'classes';

    protected $fillable = ['course_id', 'code'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
