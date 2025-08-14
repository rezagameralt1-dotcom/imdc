<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('products')->select('id','shop_id','name','sku','price','meta','created_at');
        if ($shopId = $request->query('shop_id')) {
            $q->where('shop_id', (int)$shopId);
        }
        if ($search = $request->query('search')) {
            $q->where(function($w) use ($search) {
                $w->where('name','ilike', "%{$search}%")
                  ->orWhere('sku','ilike', "%{$search}%");
            });
        }
        $q->orderByDesc('id');
        $list = $q->paginate(min((int)$request->query('per_page', 20), 100));
        return ApiResponse::success($list);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'name'    => 'required|string|max:255',
            'sku'     => 'required|string|max:64|unique:products,sku',
            'price'   => 'required|integer|min:0',
            'meta'    => 'nullable|array',
        ]);

        $id = DB::table('products')->insertGetId([
            'shop_id' => $data['shop_id'],
            'name'    => $data['name'],
            'sku'     => $data['sku'],
            'price'   => $data['price'],
            'meta'    => isset($data['meta']) ? json_encode($data['meta']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ensure inventory row exists
        DB::table('inventory')->updateOrInsert(['product_id'=>$id], ['stock'=>0,'created_at'=>now(),'updated_at'=>now()]);

        return ApiResponse::success(['id'=>$id], 201);
    }

    public function update(Request $request, int $id)
    {
        $exists = DB::table('products')->where('id',$id)->exists();
        if (!$exists) return ApiResponse::error('Product not found', 404);

        $data = $request->validate([
            'name'  => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|integer|min:0',
            'meta'  => 'nullable|array',
        ]);

        $payload = [];
        foreach (['name','price'] as $k) {
            if (array_key_exists($k, $data)) $payload[$k] = $data[$k];
        }
        if (array_key_exists('meta', $data)) $payload['meta'] = json_encode($data['meta']);

        if ($payload) {
            $payload['updated_at'] = now();
            DB::table('products')->where('id',$id)->update($payload);
        }
        return ApiResponse::success(['id'=>$id]);
    }

    public function destroy(int $id)
    {
        $count = DB::table('products')->where('id',$id)->delete();
        if ($count === 0) return ApiResponse::error('Product not found', 404);
        return ApiResponse::success();
    }
}
