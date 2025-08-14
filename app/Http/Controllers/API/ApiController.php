<?php
namespace App\Http\Controllers\API;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;

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

