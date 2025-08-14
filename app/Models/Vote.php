<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    protected $fillable = ['proposal_id','user_id','value'];
    protected $casts = ['value'=>'boolean'];

    public function proposal(): BelongsTo { return $this->belongsTo(Proposal::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
