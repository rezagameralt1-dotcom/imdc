<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalCase extends Model
{
    protected $fillable = ['case_no','owner_id','status'];

    public function owner(): BelongsTo { return $this->belongsTo(User::class,'owner_id'); }
}
