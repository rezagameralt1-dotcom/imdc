<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafeRoom extends Model
{
    protected $fillable = ['name', 'panic_code'];
}
