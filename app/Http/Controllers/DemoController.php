<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function hello(Request $request)
    {
        return response()->json([
            'ok' => true,
            'message' => 'hello from DemoController',
            'ip' => $request->ip(),
        ]);
    }

    public function secure(Request $request)
    {
        return response()->json([
            'ok' => true,
            'user' => $request->user()?->only(['id','name','email']),
        ]);
    }
}
