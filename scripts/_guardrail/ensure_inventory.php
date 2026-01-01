#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Ensure Inventory Exists
 * 
 * Ensures inventory item exists for a product with sufficient quantity.
 * Outputs: "ok" or "failed: <message>" to STDOUT.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$productId = $argv[1] ?? null;

if (!$productId) {
    fwrite(STDERR, "Usage: ensure_inventory.php <product_id>\n");
    exit(1);
}

try {
    $item = \App\Inventory\Models\InventoryItem::firstOrCreate(
        ['product_id' => $productId],
        ['available_quantity' => 100, 'reserved_quantity' => 0]
    );
    
    if ($item->available_quantity < 10) {
        $item->available_quantity = 100;
        $item->save();
    }
    
    echo 'ok' . PHP_EOL;
} catch (Exception $e) {
    echo 'failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
