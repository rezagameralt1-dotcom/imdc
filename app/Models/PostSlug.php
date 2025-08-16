<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSlug extends Model
{
    protected $fillable = ['post_id', 'slug'];
}
