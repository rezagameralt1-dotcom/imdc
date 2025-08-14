<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\CategoryResource;
use App\Models\Category;

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
