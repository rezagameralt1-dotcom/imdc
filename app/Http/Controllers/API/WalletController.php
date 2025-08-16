<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index()
    {
        if (! \Schema::hasTable('wallets')) {
            return ApiResponse::success(['wallets' => []]);
        }
        $rows = DB::table('wallets')->orderByDesc('id')->limit(50)->get();

        return ApiResponse::success(['wallets' => $rows]);
    }

    public function store()
    {
        if (! \Schema::hasTable('wallets')) {
            return ApiResponse::error('wallets table missing', 400);
        }
        $id = DB::table('wallets')->insertGetId([
            'address' => request('address', '0x0'),
            'network' => request('network', 'unknown'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success(['id' => $id]);
    }
}
