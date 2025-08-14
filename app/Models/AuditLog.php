<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['event','user_id','payload','created_at'];
    protected $casts = ['payload'=>'array','created_at'=>'datetime'];
}
