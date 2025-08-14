<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DidProfile extends Model
{
    protected $fillable = ['user_id','did','credentials'];
    protected $casts = ['credentials' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
