<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Marketplace\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inv) {}

    public function show($productId) {
        $p = Product::findOrFail($productId);
        return ['success'=>true,'data'=>$this->inv->ensureInventory($p)];
    }

    public function add(Request $r, $productId) {
        $data = $r->validate(['qty'=>'required|integer|min:1','reason'=>'nullable|string']);
        $p = Product::findOrFail($productId);
        $inv = $this->inv->addStock($p, (int)$data['qty'], optional($r->user())->id, $data['reason'] ?? 'restock');
        return ['success'=>true,'data'=>$inv];
    }

    public function adjust(Request $r, $productId) {
        $data = $r->validate(['delta'=>'required|integer','reason'=>'nullable|string']);
        $p = Product::findOrFail($productId);
        $inv = $this->inv->adjustStock($p, (int)$data['delta'], optional($r->user())->id, $data['reason'] ?? 'adjust');
        return ['success'=>true,'data'=>$inv];
    }

    public function movements($productId) {
        $mov = StockMovement::where('product_id',$productId)->orderByDesc('id')->paginate(50);
        return ['success'=>true,'data'=>$mov];
    }
}
