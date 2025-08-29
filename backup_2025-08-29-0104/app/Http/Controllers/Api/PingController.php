<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PingController extends Controller
{
    public function __invoke(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['message' => 'pong'], 'trace_id' => uniqid()]);
    }
}
