<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $r) {
        $q = Product::query()
            ->with('category','inventory')
            ->when($r->filled('q'), fn($qq)=>$qq->where('title','ilike','%'.$r->q.'%'))
            ->when($r->filled('category_id'), fn($qq)=>$qq->where('category_id',$r->category_id))
            ->when($r->filled('active'), fn($qq)=>$qq->where('is_active', filter_var($r->active, FILTER_VALIDATE_BOOL)));
        return ['success'=>true,'data'=>$q->orderByDesc('id')->paginate(20)];
    }

    public function store(Request $r) {
        $data = $r->validate([
            'sku'=>'required|string|unique:products',
            'title'=>'required|string',
            'description'=>'nullable|string',
            'category_id'=>'nullable|exists:categories,id',
            'price'=>'required|numeric|min:0',
            'currency'=>'nullable|string|size:3',
            'is_active'=>'boolean',
            'meta'=>'array'
        ]);
        $p = Product::create($data);
        $p->load('inventory');
        return ['success'=>true,'data'=>$p];
    }

    public function show($id) {
        return ['success'=>true,'data'=>Product::with('category','inventory')->findOrFail($id)];
    }

    public function update(Request $r, $id) {
        $p = Product::findOrFail($id);
        $data = $r->validate([
            'sku'=>"sometimes|string|unique:products,sku,$id",
            'title'=>'sometimes|string',
            'description'=>'nullable|string',
            'category_id'=>'nullable|exists:categories,id',
            'price'=>'sometimes|numeric|min:0',
            'currency'=>'nullable|string|size:3',
            'is_active'=>'boolean',
            'meta'=>'array'
        ]);
        $p->update($data);
        return ['success'=>true,'data'=>$p->fresh('inventory','category')];
    }

    public function destroy($id) {
        Product::findOrFail($id)->delete();
        return ['success'=>true,'data'=>true];
    }
}
