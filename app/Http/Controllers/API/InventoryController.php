<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function adjust(Request $request, int $productId)
    {
        $data = $request->validate([
            'delta' => 'required|integer',
        ]);

        return DB::transaction(function () use ($productId, $data) {
            $row = DB::table('inventory')->where('product_id',$productId)->lockForUpdate()->first();
            if (!$row) return ApiResponse::error('Inventory not found', 404);

            $newStock = $row->stock + $data['delta'];
            if ($newStock < 0) {
                return ApiResponse::error('Resulting stock would be negative', 422);
            }
            DB::table('inventory')->where('product_id',$productId)->update([
                'stock' => $newStock,
                'updated_at' => now(),
            ]);
            return ApiResponse::success(['product_id'=>$productId,'stock'=>$newStock]);
        });
    }
}
