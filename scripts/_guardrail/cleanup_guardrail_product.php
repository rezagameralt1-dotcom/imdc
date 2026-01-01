#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Cleanup Guardrail Product
 * 
 * Deletes a product that was created for guardrail testing.
 * Only deletes if metadata.seed == 'guardrail' AND run_id matches.
 * Safe to run multiple times.
 * 
 * Usage: php cleanup_guardrail_product.php <product_id> [run_id]
 *   OR: run_id can be read from env IMDC_GR_RUN_ID
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Products\Models\Product;
use Illuminate\Support\Facades\DB;

$productId = $argv[1] ?? null;
$runId = $argv[2] ?? getenv('IMDC_GR_RUN_ID') ?: null;

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
    
    // Safety check: only delete guardrail products with matching run_id
    $metadata = $product->metadata ?? [];
    $isGuardrail = isset($metadata['seed']) && $metadata['seed'] === 'guardrail';
    $runIdMatches = false;
    
    if ($runId && isset($metadata['run_id'])) {
        $runIdMatches = $metadata['run_id'] === $runId;
    }
    
    if (!$isGuardrail) {
        fwrite(STDERR, "[GUARDRAIL] WARNING: Product {$productId} is not a guardrail product (metadata.seed != 'guardrail'), skipping deletion\n");
        exit(0);
    }
    
    if ($runId && !$runIdMatches) {
        fwrite(STDERR, "[GUARDRAIL] WARNING: Product {$productId} run_id mismatch, skipping deletion\n");
        fwrite(STDERR, "  Expected run_id: {$runId}\n");
        fwrite(STDERR, "  Product run_id: " . ($metadata['run_id'] ?? 'not set') . "\n");
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
