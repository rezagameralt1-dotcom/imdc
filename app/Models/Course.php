<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Course extends Model
{
    protected $fillable = ['title','description','teacher_id'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class,'teacher_id'); }
}
