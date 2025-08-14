<?php
namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $extra = []): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data] + $extra;
        $payload['trace_id'] = self::traceId();
        return response()->json($payload, $status);
    }

    public static function error(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        $payload = ['success' => false, 'error' => $message] + $extra;
        $payload['trace_id'] = self::traceId();
        return response()->json($payload, $status);
    }

    private static function traceId(): string
    {
        try {
            $req = request();
            $tid = $req->header('X-Trace-Id') ?: $req->headers->get('X-Trace-Id');
            return $tid ?: (string) Str::uuid();
        } catch (\Throwable $e) {
            return (string) Str::uuid();
        }
    }
}
