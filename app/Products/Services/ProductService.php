<?php

namespace App\Products\Services;

use App\Models\User;
use App\Products\Models\Product;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', '%'.$filters['search'].'%')
                    ->orWhere('sku', 'ilike', '%'.$filters['search'].'%');
            });
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function allActive(): Collection
    {
        return Product::query()->where('status', 'active')->get();
    }

    public function create(array $data, ?User $actor = null, ?string $traceId = null): Product
    {
        $exists = Product::where('sku', $data['sku'])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['sku' => 'SKU already exists']);
        }

        /** @var Product $product */
        $product = DB::connection('products')->transaction(function () use ($data) {
            return Product::create($data);
        });

        $this->auditLogger->log('product.created', $product, $actor, ['sku' => $product->sku], $traceId);

        return $product;
    }

    public function update(Product $product, array $data, ?User $actor = null, ?string $traceId = null): Product
    {
        if (isset($data['sku']) && $data['sku'] !== $product->sku) {
            $exists = Product::where('sku', $data['sku'])
                ->where('id', '!=', $product->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['sku' => 'SKU already exists']);
            }
        }

        DB::connection('products')->transaction(function () use ($product, $data) {
            $product->fill($data);
            $product->save();
        });

        $this->auditLogger->log('product.updated', $product, $actor, ['sku' => $product->sku], $traceId);

        return $product;
    }
}



