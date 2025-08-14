<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NftTransferController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('nft_transfers')->select('id', 'from_wallet_id', 'to_wallet_id', 'token_id', 'contract', 'transferred_at', 'created_at');
        if ($from = $request->query('from_wallet_id')) {
            $q->where('from_wallet_id', (int) $from);
        }
        if ($to = $request->query('to_wallet_id')) {
            $q->where('to_wallet_id', (int) $to);
        }
        if ($token = $request->query('token_id')) {
            $q->where('token_id', $token);
        }
        $q->orderByDesc('id');

        return ApiResponse::success($q->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_wallet_id' => 'nullable|integer|exists:wallets,id',
            'to_wallet_id' => 'nullable|integer|exists:wallets,id',
            'token_id' => 'required|string|max:255',
            'contract' => 'nullable|string|max:255',
            'transferred_at' => 'nullable|date',
        ]);
        if (empty($data['from_wallet_id']) && empty($data['to_wallet_id'])) {
            return ApiResponse::error('Either from_wallet_id or to_wallet_id must be provided', 422);
        }
        $id = DB::table('nft_transfers')->insertGetId([
            'from_wallet_id' => $data['from_wallet_id'] ?? null,
            'to_wallet_id' => $data['to_wallet_id'] ?? null,
            'token_id' => $data['token_id'],
            'contract' => $data['contract'] ?? null,
            'transferred_at' => $data['transferred_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success(['id' => $id], 201);
    }
}
