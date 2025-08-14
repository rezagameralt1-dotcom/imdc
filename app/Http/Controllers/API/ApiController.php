<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class ApiController extends BaseController
{
    protected function ok($data = [], int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function created($data = [], int $status = 201): JsonResponse
    {
        return response()->json($data, $status);
    }
}
