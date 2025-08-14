<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SafeRoomController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('safe_rooms')->select('id', 'name', 'panic_code', 'created_at')->orderByDesc('id');

        return ApiResponse::success($q->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'panic_code' => 'nullable|string|max:64',
        ]);
        $id = DB::table('safe_rooms')->insertGetId([
            'name' => $data['name'],
            'panic_code' => $data['panic_code'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success(['id' => $id], 201);
    }

    public function setPanicCode(Request $request, int $id)
    {
        $data = $request->validate([
            'panic_code' => 'required|string|max:64',
        ]);
        $count = DB::table('safe_rooms')->where('id', $id)->update([
            'panic_code' => $data['panic_code'],
            'updated_at' => now(),
        ]);
        if ($count === 0) {
            return ApiResponse::error('Safe room not found', 404);
        }

        return ApiResponse::success(['id' => $id, 'panic_code' => $data['panic_code']]);
    }
}
