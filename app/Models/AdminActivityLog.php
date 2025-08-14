<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'meta', 'ip'];

    public static function add(?int $userId, string $action, array $meta = [], ?string $ip = null): void
    {
        static::create([
            'user_id' => $userId,
            'action' => $action,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $ip,
        ]);
    }
}
