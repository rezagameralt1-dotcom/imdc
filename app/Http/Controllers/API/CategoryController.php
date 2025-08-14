<?php
namespace App\Http\Controllers\API;

use App\Models\Category;
use App\Http\Resources\CategoryResource;

class CategoryController extends ApiController
{
    public function index()
    {
        return CategoryResource::collection(Category::orderBy('name')->paginate(50));
    }

    public function show(Category $category)
    {
        return new CategoryResource($category);
    }
}

