<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ApiResponse
{
    protected function successResponse(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'error' => null,
            'trace_id' => $this->traceId(),
        ], $status);
    }

    protected function errorResponse(string $message, int $status = 400, mixed $details = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'error' => [
                'message' => $message,
                'details' => $details,
            ],
            'trace_id' => $this->traceId(),
        ], $status);
    }

    protected function traceId(): ?string
    {
        /** @var Request|null $request */
        $request = request();

        return $request?->attributes->get('trace_id');
    }
}

