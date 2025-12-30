<?php

namespace App\Products\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Products\Http\Requests\StoreProductRequest;
use App\Products\Http\Requests\UpdateProductRequest;
use App\Products\Models\Product;
use App\Products\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    public function __construct(private readonly ProductService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->service->list($request->only(['status', 'search', 'per_page']));

        return $this->successResponse($products);
    }

    public function store(StoreProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $product = $this->service->create(
            $request->validated(),
            $request->user(),
            $request->attributes->get('trace_id')
        );

        return $this->successResponse($product, 201);
    }

    public function show(string $id)
    {
        $product = Product::findOrFail($id);

        $this->authorize('view', $product);

        return $this->successResponse($product);
    }

    public function update(UpdateProductRequest $request, string $id)
    {
        $product = Product::findOrFail($id);

        $this->authorize('update', $product);

        $updated = $this->service->update(
            $product,
            $request->validated(),
            $request->user(),
            $request->attributes->get('trace_id')
        );

        return $this->successResponse($updated);
    }
}



