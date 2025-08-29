<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $r) {
        $q = Category::query()->when($r->boolean('active'), fn($qq)=>$qq->where('is_active',true));
        return ['success'=>true,'data'=>$q->orderBy('name')->paginate(20)];
    }
    public function store(Request $r) {
        $data = $r->validate(['slug'=>'required|string|unique:categories','name'=>'required|string','description'=>'nullable|string','is_active'=>'boolean']);
        $cat = Category::create($data);
        return ['success'=>true,'data'=>$cat];
    }
    public function show($id) {
        return ['success'=>true,'data'=>Category::findOrFail($id)];
    }
    public function update(Request $r, $id) {
        $cat = Category::findOrFail($id);
        $data = $r->validate(['slug'=>"sometimes|string|unique:categories,slug,$id",'name'=>'sometimes|string','description'=>'nullable|string','is_active'=>'boolean']);
        $cat->update($data);
        return ['success'=>true,'data'=>$cat];
    }
    public function destroy($id) {
        Category::findOrFail($id)->delete();
        return ['success'=>true,'data'=>true];
    }
}
