<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['name', 'definition'];

    protected $casts = ['definition' => 'array'];
}
