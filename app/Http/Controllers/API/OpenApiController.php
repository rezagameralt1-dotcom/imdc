<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function json(): JsonResponse
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => ['title' => 'IMDC Health API', 'version' => '1.0.0'],
            'paths' => [
                '/api/health' => ['get' => ['summary' => 'Ping', 'responses' => ['200' => ['description' => 'OK']]]],
                '/api/health/live' => ['get' => ['summary' => 'Liveness', 'responses' => ['200' => ['description' => 'OK']]]],
                '/api/health/ready' => ['get' => ['summary' => 'Readiness', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
        ];

        return response()->json($spec);
    }
}
