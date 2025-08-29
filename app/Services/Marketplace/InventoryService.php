<?php

namespace App\Services\Marketplace;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function ensureInventory(Product $product): Inventory {
        return $product->inventory ?? Inventory::create(['product_id' => $product->id]);
    }

    public function addStock(Product $product, int $qty, ?int $performedBy = null, string $reason='restock'): Inventory {
        if ($qty <= 0) throw ValidationException::withMessages(['qty' => 'Quantity must be positive']);
        return DB::transaction(function() use ($product,$qty,$performedBy,$reason) {
            $inv = $this->ensureInventory($product);
            $inv->stock_on_hand += $qty;
            $inv->save();
            StockMovement::create([
                'product_id'=>$product->id, 'type'=>'IN', 'quantity'=>$qty,
                'reason'=>$reason, 'performed_by'=>$performedBy
            ]);
            return $inv->refresh();
        });
    }

    public function adjustStock(Product $product, int $delta, ?int $performedBy = null, string $reason='adjust'): Inventory {
        return DB::transaction(function() use ($product,$delta,$performedBy,$reason) {
            $inv = $this->ensureInventory($product);
            $new = $inv->stock_on_hand + $delta;
            if ($new < 0) throw ValidationException::withMessages(['stock' => 'Stock cannot be negative']);
            $inv->stock_on_hand = $new;
            $inv->save();
            StockMovement::create([
                'product_id'=>$product->id, 'type'=>'ADJUST', 'quantity'=>$delta,
                'reason'=>$reason, 'performed_by'=>$performedBy
            ]);
            return $inv->refresh();
        });
    }

    public function reserve(Product $product, int $qty, string $refType, string $refId, ?int $performedBy=null): Inventory {
        if ($qty <= 0) throw ValidationException::withMessages(['qty'=>'Quantity must be positive']);
        return DB::transaction(function() use ($product,$qty,$refType,$refId,$performedBy) {
            $inv = $this->ensureInventory($product)->lockForUpdate()->firstWhere('product_id',$product->id) ?? $this->ensureInventory($product);
            if ($inv->stock_available < $qty) {
                throw ValidationException::withMessages(['stock' => 'Insufficient stock']);
            }
            $inv->stock_reserved += $qty;
            $inv->save();
            StockMovement::create([
                'product_id'=>$product->id,'type'=>'RESERVE','quantity'=>$qty,
                'reason'=>"reserve:$refType#$refId",'ref_type'=>$refType,'ref_id'=>$refId,'performed_by'=>$performedBy
            ]);
            return $inv->refresh();
        });
    }

    public function release(Product $product, int $qty, string $refType, string $refId, ?int $performedBy=null): Inventory {
        return DB::transaction(function() use ($product,$qty,$refType,$refId,$performedBy) {
            $inv = $this->ensureInventory($product)->lockForUpdate()->firstWhere('product_id',$product->id) ?? $this->ensureInventory($product);
            $inv->stock_reserved = max($inv->stock_reserved - $qty, 0);
            $inv->save();
            StockMovement::create([
                'product_id'=>$product->id,'type'=>'RELEASE','quantity'=>$qty,
                'reason'=>"release:$refType#$refId",'ref_type'=>$refType,'ref_id'=>$refId,'performed_by'=>$performedBy
            ]);
            return $inv->refresh();
        });
    }

    public function commitOut(Product $product, int $qty, string $refType, string $refId, ?int $performedBy=null): Inventory {
        return DB::transaction(function() use ($product,$qty,$refType,$refId,$performedBy) {
            $inv = $this->ensureInventory($product)->lockForUpdate()->firstWhere('product_id',$product->id) ?? $this->ensureInventory($product);
            $inv->stock_reserved = max($inv->stock_reserved - $qty, 0);
            $inv->stock_on_hand  = max($inv->stock_on_hand - $qty, 0);
            $inv->save();
            StockMovement::create([
                'product_id'=>$product->id,'type'=>'OUT','quantity'=>$qty,
                'reason'=>"sale:$refType#$refId",'ref_type'=>$refType,'ref_id'=>$refId,'performed_by'=>$performedBy
            ]);
            return $inv->refresh();
        });
    }
}
