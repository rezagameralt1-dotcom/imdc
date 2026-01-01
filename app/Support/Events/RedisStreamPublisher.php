<?php

namespace App\Support\Events;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisStreamPublisher
{
    public function publish(string $stream, array $payload): void
    {
        try {
            Redis::xadd($stream, '*', ['payload' => json_encode($payload)]);
        } catch (\Throwable $e) {
            Log::error('Failed to publish to Redis stream', [
                'stream' => $stream,
                'message' => $e->getMessage(),
            ]);
        }
    }
}



