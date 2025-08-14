<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DidProfileController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('did_profiles')->select('id','user_id','did','credentials','created_at','updated_at');
        if ($uid = $request->query('user_id')) $q->where('user_id', (int)$uid);
        $q->orderByDesc('id');
        return ApiResponse::success($q->paginate(min((int)$request->query('per_page',20),100)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'did' => 'required|string|max:255|unique:did_profiles,did',
            'credentials' => 'nullable|array',
        ]);
        $id = DB::table('did_profiles')->insertGetId([
            'user_id' => $data['user_id'],
            'did' => $data['did'],
            'credentials' => isset($data['credentials']) ? json_encode($data['credentials']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return ApiResponse::success(['id'=>$id], 201);
    }

    public function update(Request $request, int $id)
    {
        $exists = DB::table('did_profiles')->where('id',$id)->exists();
        if (!$exists) return ApiResponse::error('DID profile not found', 404);

        $data = $request->validate([
            'credentials' => 'nullable|array',
        ]);
        $payload = [];
        if (array_key_exists('credentials', $data)) $payload['credentials'] = json_encode($data['credentials']);
        if ($payload) {
            $payload['updated_at'] = now();
            DB::table('did_profiles')->where('id',$id)->update($payload);
        }
        return ApiResponse::success(['id'=>$id]);
    }
}
