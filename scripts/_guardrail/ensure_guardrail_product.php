#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Ensure Guardrail Product Exists
 * 
 * Checks if at least one usable product exists. If not, creates one with all required fields.
 * Outputs product ID to STDOUT (machine-parseable).
 * Outputs diagnostics to STDERR.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Products\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

try {
    // Check if any product exists
    $existingProduct = Product::first();
    
    if ($existingProduct) {
        fwrite(STDERR, "[GUARDRAIL] Found existing product: {$existingProduct->id} (SKU: {$existingProduct->sku})\n");
        echo $existingProduct->id . PHP_EOL;
        exit(0);
    }
    
    // No product exists - create one with ALL required NOT NULL fields
    fwrite(STDERR, "[GUARDRAIL] No products found, creating guardrail product...\n");
    
    // Generate deterministic SKU: IMDC-GR- + first 12 chars of UUID (uppercase, no hyphens)
    $uuid = (string) Str::uuid();
    $skuSuffix = strtoupper(str_replace('-', '', substr($uuid, 0, 12)));
    $sku = "IMDC-GR-{$skuSuffix}";
    
    // Generate run_id for metadata
    $runId = (string) Str::uuid();
    $now = now()->toIso8601String();
    
    // Create product with ALL required NOT NULL fields
    $product = DB::connection('products')->transaction(function () use ($sku, $runId, $now) {
        return Product::create([
            'sku' => $sku,  // REQUIRED NOT NULL
            'name' => 'Guardrail Test Product',  // REQUIRED NOT NULL
            'description' => 'Auto-created product for marketplace idempotency test',  // nullable
            'price' => 10.00,  // REQUIRED NOT NULL (decimal)
            'currency' => 'USD',  // default 'USD', but explicit
            'status' => 'active',  // default 'draft', but set to 'active'
            'metadata' => [
                'seed' => 'guardrail',
                'purpose' => 'marketplace-idempotency',
                'at' => $now,
                'run_id' => $runId,
            ],
            // created_at and updated_at are auto-managed by Eloquent
        ]);
    });
    
    fwrite(STDERR, "[GUARDRAIL] Created product for idempotency test:\n");
    fwrite(STDERR, "  ID: {$product->id}\n");
    fwrite(STDERR, "  SKU: {$product->sku}\n");
    fwrite(STDERR, "  Name: {$product->name}\n");
    fwrite(STDERR, "  Price: {$product->price} {$product->currency}\n");
    fwrite(STDERR, "  RUN_ID: {$runId}\n");
    
    // Output product ID to STDOUT (machine-parseable, ONLY the UUID)
    echo $product->id . PHP_EOL;
    
} catch (\Exception $e) {
    fwrite(STDERR, "[GUARDRAIL] ERROR: Failed to ensure guardrail product: " . $e->getMessage() . "\n");
    fwrite(STDERR, "  Trace: " . $e->getTraceAsString() . "\n");
    exit(1);
}
