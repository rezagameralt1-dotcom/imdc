#!/usr/bin/env php
<?php
/**
 * Guardrail Helper: Seed Product for Idempotency Test
 * 
 * Creates a product with all required fields for marketplace idempotency testing.
 * Outputs product ID to stdout (machine-parseable).
 * Outputs diagnostics to stderr.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Products\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

try {
    // Generate deterministic SKU: IMDC-GR- + first 12 chars of UUID (uppercase, no hyphens)
    $uuid = (string) Str::uuid();
    $skuSuffix = strtoupper(str_replace('-', '', substr($uuid, 0, 12)));
    $sku = "IMDC-GR-{$skuSuffix}";
    
    // Generate run_id for metadata
    $runId = (string) Str::uuid();
    $now = now()->toIso8601String();
    
    // Check if product with this SKU already exists (shouldn't happen, but be safe)
    $existing = Product::where('sku', $sku)->first();
    if ($existing) {
        // Check if it's a guardrail product
        $metadata = $existing->metadata ?? [];
        if (isset($metadata['seed']) && $metadata['seed'] === 'guardrail') {
            fwrite(STDERR, "[GUARDRAIL] Product with SKU {$sku} already exists (guardrail product)\n");
            echo $existing->id . PHP_EOL;
            exit(0);
        } else {
            // SKU collision with non-guardrail product - generate new one
            $uuid = (string) Str::uuid();
            $skuSuffix = strtoupper(str_replace('-', '', substr($uuid, 0, 12)));
            $sku = "IMDC-GR-{$skuSuffix}";
        }
    }
    
    // Create product with all required fields
    $product = DB::connection('products')->transaction(function () use ($sku, $runId, $now) {
        return Product::create([
            'sku' => $sku,
            'name' => 'Guardrail Test Product',
            'description' => 'Auto-created product for marketplace idempotency test',
            'price' => 10.00,
            'currency' => 'USD',
            'status' => 'active',
            'metadata' => [
                'seed' => 'guardrail',
                'purpose' => 'marketplace-idempotency',
                'at' => $now,
                'run_id' => $runId,
            ],
        ]);
    });
    
    fwrite(STDERR, "[GUARDRAIL] Created product for idempotency test:\n");
    fwrite(STDERR, "  ID: {$product->id}\n");
    fwrite(STDERR, "  SKU: {$product->sku}\n");
    fwrite(STDERR, "  Name: {$product->name}\n");
    fwrite(STDERR, "  Price: {$product->price} {$product->currency}\n");
    fwrite(STDERR, "  RUN_ID: {$runId}\n");
    
    // Output product ID to stdout (machine-parseable)
    echo $product->id . PHP_EOL;
    
} catch (\Exception $e) {
    fwrite(STDERR, "[GUARDRAIL] ERROR: Failed to seed product: " . $e->getMessage() . "\n");
    fwrite(STDERR, "  Trace: " . $e->getTraceAsString() . "\n");
    exit(1);
}
