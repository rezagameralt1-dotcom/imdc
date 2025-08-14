<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $shops = DB::table('shops')->select('id','name','owner_id','created_at')
            ->orderBy('name')->paginate(min((int)$request->query('per_page', 20), 100));
        return ApiResponse::success($shops);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'owner_id' => 'required|integer|exists:users,id',
        ]);
        $id = DB::table('shops')->insertGetId([
            'name'=>$data['name'],
            'owner_id'=>$data['owner_id'],
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
        return ApiResponse::success(['id'=>$id], 201);
    }
}
