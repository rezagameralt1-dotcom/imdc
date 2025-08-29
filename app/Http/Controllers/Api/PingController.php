<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>['message'=>'pong'],'trace_id'=>uniqid()]);
    }
}
