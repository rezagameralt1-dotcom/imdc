<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'auditable_type',
        'auditable_id',
        'payload',
        'user_id',
        'trace_id',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

