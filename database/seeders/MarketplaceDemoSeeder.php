<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Services\Marketplace\InventoryService;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $cat = Category::firstOrCreate(['slug'=>'general'], ['name'=>'عمومی','description'=>null]);
        $p = Product::firstOrCreate(['sku'=>'SKU-001'], [
            'title'=>'نمونه محصول ۱',
            'category_id'=>$cat->id,
            'price'=>1990000,
            'currency'=>'IRR',
            'is_active'=>true
        ]);
        app(InventoryService::class)->addStock($p, 50, null, 'seed');
    }
}
