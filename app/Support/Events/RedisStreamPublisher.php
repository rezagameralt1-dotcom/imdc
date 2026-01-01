<?php

namespace App\Support\Events;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisStreamPublisher
{
    public function publish(string $stream, array $payload): void
    {
        try {
            // Use Redis facade connection() method for predis compatibility (works with REDIS_CLIENT=predis)
            // xadd command format: XADD stream id field value [field value ...]
            // For predis, xadd() method expects: (stream, id, [field => value, ...])
            $redis = Redis::connection();
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
            // Call xadd with proper format: stream, id, array of field-value pairs
            $redis->xadd($stream, '*', ['payload' => $payloadJson]);
        } catch (\Throwable $e) {
            // Log warning instead of error to indicate non-fatal nature
            // Publisher failure must NOT cause HTTP 500
            Log::warning('Failed to publish to Redis stream (non-fatal)', [
                'stream' => $stream,
                'message' => $e->getMessage(),
            ]);
        }
    }
}



