<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Services\Marketplace\InventoryService;

$productId = (int)($_SERVER['argv'][1] ?? 0);
$p = Product::findOrFail($productId);
$svc = app(InventoryService::class);
$inv = $svc->ensureInventory($p);

$out = $inv->toArray();
$out['stock_available'] = (int)$inv->stock_on_hand - (int)$inv->stock_reserved;

echo json_encode(['ok'=>true,'data'=>$out], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;
