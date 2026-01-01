#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Cleanup Seeded Product
 * 
 * Deletes a product that was created for guardrail testing.
 * Only deletes if metadata.seed == 'guardrail' OR matches run_id.
 * 
 * Usage: php cleanup_seeded_product.php <product_id> [run_id]
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Products\Models\Product;
use Illuminate\Support\Facades\DB;

$productId = $argv[1] ?? null;
$runId = $argv[2] ?? null;

if (!$productId) {
    fwrite(STDERR, "[GUARDRAIL] ERROR: Product ID required\n");
    exit(1);
}

try {
    $product = Product::find($productId);
    
    if (!$product) {
        fwrite(STDERR, "[GUARDRAIL] Product {$productId} not found (may already be deleted)\n");
        exit(0);
    }
    
    // Safety check: only delete guardrail products
    $metadata = $product->metadata ?? [];
    $isGuardrail = isset($metadata['seed']) && $metadata['seed'] === 'guardrail';
    $matchesRunId = $runId && isset($metadata['run_id']) && $metadata['run_id'] === $runId;
    
    if (!$isGuardrail && !$matchesRunId) {
        fwrite(STDERR, "[GUARDRAIL] WARNING: Product {$productId} is not a guardrail product, skipping deletion\n");
        fwrite(STDERR, "  Metadata: " . json_encode($metadata) . "\n");
        exit(0);
    }
    
    // Delete the product
    DB::connection('products')->transaction(function () use ($product) {
        $product->delete();
    });
    
    fwrite(STDERR, "[GUARDRAIL] Deleted guardrail product: {$productId} (SKU: {$product->sku})\n");
    
} catch (\Exception $e) {
    fwrite(STDERR, "[GUARDRAIL] ERROR: Failed to cleanup product {$productId}: " . $e->getMessage() . "\n");
    exit(1);
}
